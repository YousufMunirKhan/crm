<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmailManagementController;
use App\Models\Campaign;
use App\Modules\Marketing\Jobs\SendMarketingPlanItemJob;
use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Models\MarketingPlanEvent;
use App\Modules\Marketing\Models\MarketingPlanItem;
use App\Modules\Marketing\Services\MarketingGuardrails;
use App\Modules\Marketing\Services\MarketingPlannerService;
use App\Modules\Marketing\Services\MarketingResultsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * The review screen's API.
 *
 * Nothing here sends without an explicit approve-then-send from a person. The
 * planner only ever produces a draft.
 */
class MarketingAgentController extends Controller
{
    private const MANAGER_ROLES = ['Admin', 'System Admin', 'Manager'];

    private function assertManager(Request $request): void
    {
        $user = $request->user();

        foreach (self::MANAGER_ROLES as $role) {
            if ($user?->isRole($role)) {
                return;
            }
        }

        abort(403, 'Only managers and admins can work with marketing plans.');
    }

    public function index(Request $request)
    {
        $this->assertManager($request);

        $plans = MarketingPlan::query()
            ->with(['generatedBy:id,name', 'approvedBy:id,name'])
            ->withCount([
                'items as approved_count' => fn ($q) => $q->where('status', 'approved'),
                'items as sent_items_count' => fn ($q) => $q->where('status', 'sent'),
            ])
            ->orderByDesc('week_starting')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            // The newest plan that is still live, so the screen opens on
            // something actionable rather than on last week's history.
            'current_id' => $plans->firstWhere(fn ($p) => $p->status !== MarketingPlan::STATUS_SUPERSEDED)?->id
                ?? $plans->first()?->id,
            'data' => $plans,
            'limits' => $this->limits(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $this->assertManager($request);

        $plan = MarketingPlan::with([
            'items.customer:id,name,business_name,email,phone,type,city',
            'items.emailTemplate:id,name,purpose,subject',
            'generatedBy:id,name',
            'approvedBy:id,name',
        ])->findOrFail($id);

        return response()->json([
            'plan' => $plan,
            'results' => app(MarketingResultsService::class)->forPlan($plan),
            // Per person, so "did Grace get it and did she open it" has an
            // answer - and so a blocked row's reason is readable without
            // hunting through a collapsed group.
            'recipients' => app(MarketingResultsService::class)->recipients($plan),
            'events' => $plan->events()->with('user:id,name')->limit(200)->get(),
            'limits' => $this->limits(),
        ]);
    }

    public function generate(Request $request, MarketingPlannerService $planner)
    {
        $this->assertManager($request);

        $week = $request->filled('week')
            ? \Illuminate\Support\Carbon::parse($request->week)->startOfDay()
            : now()->startOfWeek();

        $existing = MarketingPlan::forWeek($week->toDateString())
            ->where('status', '!=', MarketingPlan::STATUS_SUPERSEDED)
            ->first();

        if ($existing && ! in_array($existing->status, [MarketingPlan::STATUS_DRAFT, MarketingPlan::STATUS_APPROVED], true)) {
            return response()->json([
                'message' => "The plan for that week has already been sent, so it cannot be rebuilt. Its history stays as it is.",
            ], 422);
        }

        try {
            ['plan' => $plan] = $planner->generate($week, $request->user()->id);
        } catch (\Throwable $e) {
            // A failed generation used to leave nothing at all - the request
            // 500'd and the reason lived in a log file nobody reads. It is
            // recorded against the week so the screen can show what went wrong.
            Log::error('Marketing plan generation failed', [
                'week' => $week->toDateString(),
                'message' => $e->getMessage(),
            ]);

            $failed = MarketingPlan::create([
                'week_starting' => $week->toDateString(),
                'status' => MarketingPlan::STATUS_CANCELLED,
                'generated_at' => now(),
                'generated_by' => $request->user()->id,
                'generation_error' => $e->getMessage(),
            ]);

            MarketingPlanEvent::record(
                $failed->id,
                MarketingPlanEvent::GENERATION_FAILED,
                'Could not build the plan: '.\Illuminate\Support\Str::limit($e->getMessage(), 200),
                null,
                $request->user()->id,
            );

            return response()->json([
                'message' => 'Could not build the plan: '.$e->getMessage(),
                'plan' => $failed,
            ], 500);
        }

        // The previous plan is superseded, not deleted, so last week's decisions
        // and what came of them stay readable.
        if ($existing) {
            $existing->update([
                'status' => MarketingPlan::STATUS_SUPERSEDED,
                'superseded_by_id' => $plan->id,
                'superseded_at' => now(),
            ]);

            MarketingPlanEvent::record(
                $existing->id,
                MarketingPlanEvent::SUPERSEDED,
                "Replaced by a rebuild (plan #{$plan->id}).",
                null,
                $request->user()->id,
            );
        }

        MarketingPlanEvent::record(
            $plan->id,
            MarketingPlanEvent::GENERATED,
            $plan->item_count.' row(s) proposed, '
                .($plan->rail_summary['sendable'] ?? 0).' sendable.',
            null,
            $request->user()->id,
            $plan->rail_summary,
        );

        return response()->json(['plan' => $plan->load('items.customer:id,name,business_name')], 201);
    }

    /**
     * Approve, skip, or rewrite one row.
     *
     * The override lives on the row, so editing here changes this person's
     * message and nobody else's. Changing the template for everyone is a
     * separate screen on purpose.
     */
    public function updateItem(Request $request, int $planId, int $itemId)
    {
        $this->assertManager($request);

        $plan = MarketingPlan::findOrFail($planId);

        if (! $plan->isEditable()) {
            return response()->json(['message' => 'This plan has already been sent.'], 422);
        }

        $item = $plan->items()->findOrFail($itemId);

        $data = $request->validate([
            'status' => ['nullable', 'in:pending,approved,skipped'],
            'channel' => ['nullable', 'in:email,sms,whatsapp'],
            'subject_override' => ['nullable', 'string', 'max:255'],
            'body_override' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? null) === 'approved' && $item->status === MarketingPlanItem::STATUS_BLOCKED) {
            return response()->json([
                'message' => 'This one is blocked: '.$item->blocked_reason,
            ], 422);
        }

        // Empty strings mean "remove the override", not "send a blank subject".
        foreach (['subject_override', 'body_override'] as $field) {
            if (array_key_exists($field, $data) && trim((string) $data[$field]) === '') {
                $data[$field] = null;
            }
        }

        $before = $item->status;
        $item->update($data);

        $who = $item->customer?->name ?: 'a contact';

        if (isset($data['status']) && $data['status'] !== $before) {
            $action = match ($data['status']) {
                'approved' => MarketingPlanEvent::APPROVED,
                'skipped' => MarketingPlanEvent::SKIPPED,
                default => MarketingPlanEvent::REOPENED,
            };
            $verb = match ($data['status']) {
                'approved' => 'Approved',
                'skipped' => 'Skipped',
                default => 'Put back to undecided',
            };

            MarketingPlanEvent::record(
                $plan->id,
                $action,
                "{$verb}: {$who}",
                $item->id,
                $request->user()->id,
                ['from' => $before, 'to' => $data['status']],
            );
        }

        if (array_key_exists('subject_override', $data) || array_key_exists('body_override', $data)) {
            MarketingPlanEvent::record(
                $plan->id,
                MarketingPlanEvent::EDITED,
                $item->isEdited()
                    ? "Rewrote the message for {$who} only"
                    : "Reverted {$who} to the template wording",
                $item->id,
                $request->user()->id,
            );
        }

        return response()->json(['item' => $item->fresh(['customer', 'emailTemplate'])]);
    }

    /**
     * Exactly what this one person will receive.
     *
     * Rendered through the same method the real send uses, with this
     * recipient's own data and this row's override applied - a preview built
     * any other way is a drawing of the email rather than the email.
     */
    public function preview(Request $request, int $planId, int $itemId, EmailManagementController $emailManagement)
    {
        $this->assertManager($request);

        $item = MarketingPlan::findOrFail($planId)
            ->items()
            ->with(['customer', 'emailTemplate'])
            ->findOrFail($itemId);

        $customer = $item->customer;
        $template = $item->emailTemplate;

        if ($customer === null || $template === null) {
            return response()->json(['message' => 'This row has no template or customer attached.'], 422);
        }

        // Not saved: the override belongs to the row, not the template.
        $rendered = $template->replicate();
        $rendered->id = $template->id;
        $rendered->exists = true;

        if ($item->subject_override !== null) {
            $rendered->subject = $item->subject_override;
        }
        if ($item->body_override !== null) {
            $rendered->content = $item->body_override;
        }

        $missing = $this->unresolvedTags($rendered, $customer);

        return response()->json([
            'to' => $customer->email,
            'to_name' => trim(($customer->name ?? '').' '.($customer->business_name ? '· '.$customer->business_name : '')),
            'subject' => $this->mergeSubject((string) $rendered->subject, $customer),
            'html' => $emailManagement->renderTemplateForPreview($rendered, $customer),
            'edited' => $item->isEdited(),
            /**
             * A tag with nothing behind it renders as "Hello ," and you only
             * ever notice after it has gone out. Surfaced here instead.
             */
            'unresolved_tags' => $missing,
        ]);
    }

    /** Subject lines go through the same substitutions as the body. */
    private function mergeSubject(string $subject, $customer): string
    {
        $first = trim((string) ($customer->name ?? ''));
        $first = $first === '' ? '' : explode(' ', $first)[0];

        return str_replace(
            ['{{first_name}}', '{{customer_name}}', '{{company_name}}'],
            [$first, (string) ($customer->name ?? ''), (string) config('app.name')],
            $subject,
        );
    }

    /** @return array<int, string> */
    private function unresolvedTags($template, $customer): array
    {
        $needs = [
            '{{first_name}}' => $customer->name,
            '{{customer_name}}' => $customer->name,
            '{{customer_email}}' => $customer->email,
            '{{customer_phone}}' => $customer->phone,
            '{{customer_products}}' => null,
        ];

        $haystack = (string) $template->subject.' '.(is_string($template->content) ? $template->content : json_encode($template->content));
        $missing = [];

        foreach ($needs as $tag => $value) {
            if (str_contains($haystack, $tag) && trim((string) $value) === '' && $tag !== '{{customer_products}}') {
                $missing[] = $tag;
            }
        }

        return $missing;
    }

    public function bulkUpdate(Request $request, int $planId)
    {
        $this->assertManager($request);

        $plan = MarketingPlan::findOrFail($planId);

        if (! $plan->isEditable()) {
            return response()->json(['message' => 'This plan has already been sent.'], 422);
        }

        $data = $request->validate([
            'status' => ['required', 'in:approved,skipped,pending'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $query = $plan->items()->whereIn('status', [
            MarketingPlanItem::STATUS_PENDING,
            MarketingPlanItem::STATUS_APPROVED,
            MarketingPlanItem::STATUS_SKIPPED,
        ]);

        if (! empty($data['item_ids'])) {
            $query->whereIn('id', $data['item_ids']);
        }

        // Read before writing so the event can say what actually changed
        // rather than how many rows the query touched.
        $affected = (clone $query)->where('status', '!=', $data['status'])->count();
        $changed = $query->update(['status' => $data['status']]);

        if ($affected > 0) {
            $verb = match ($data['status']) {
                'approved' => 'Approved',
                'skipped' => 'Skipped',
                default => 'Reopened',
            };
            $scope = empty($data['item_ids']) ? 'everything on this plan' : 'a selection';

            MarketingPlanEvent::record(
                $plan->id,
                $data['status'] === 'skipped' ? MarketingPlanEvent::SKIPPED : MarketingPlanEvent::APPROVED,
                "{$verb} {$affected} row(s) - {$scope}",
                null,
                $request->user()->id,
                ['count' => $affected],
            );
        }

        return response()->json(['changed' => $changed]);
    }

    /**
     * Queues the approved rows.
     *
     * Rows are grouped into one campaign per purpose so the existing reporting
     * (open rates, clicks, failures) has something to hang off, but each
     * message is queued individually so one bad address cannot take the batch
     * down with it.
     */
    public function send(Request $request, int $planId)
    {
        $this->assertManager($request);

        $plan = MarketingPlan::with('items')->findOrFail($planId);

        if (! $plan->isEditable()) {
            return response()->json(['message' => 'This plan has already been sent.'], 422);
        }

        $approved = $plan->items()->sendable()->get();

        if ($approved->isEmpty()) {
            return response()->json(['message' => 'Nothing is approved yet.'], 422);
        }

        if ($approved->count() > MarketingGuardrails::WEEKLY_RECIPIENT_CAP) {
            return response()->json([
                'message' => 'That is more than the weekly cap of '.MarketingGuardrails::WEEKLY_RECIPIENT_CAP.'.',
            ], 422);
        }

        $queued = 0;

        foreach ($approved->groupBy('purpose') as $purpose => $items) {
            $campaign = Campaign::create([
                'name' => 'Agent · '.$purpose.' · w/c '.$plan->week_starting->toDateString(),
                'channel' => $items->first()->channel,
                'status' => 'sending',
                'template_id' => $items->first()->email_template_id,
                'template_type' => 'email_template',
                'recipient_count' => $items->count(),
                'started_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            foreach ($items as $item) {
                SendMarketingPlanItemJob::dispatch($item->id, $request->user()->id, $campaign->id);
                $queued++;
            }
        }

        $plan->update([
            'status' => MarketingPlan::STATUS_SENDING,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // What was left behind matters as much as what went: after a send the
        // question is always "who did we not write to, and why".
        $skipped = $plan->items()->where('status', MarketingPlanItem::STATUS_SKIPPED)->count();
        $blocked = $plan->items()->where('status', MarketingPlanItem::STATUS_BLOCKED)->count();

        MarketingPlanEvent::record(
            $plan->id,
            MarketingPlanEvent::QUEUED,
            "Sent {$queued} message(s). {$skipped} skipped by hand, {$blocked} blocked by the rules.",
            null,
            $request->user()->id,
            ['queued' => $queued, 'skipped' => $skipped, 'blocked' => $blocked],
        );

        return response()->json([
            'queued' => $queued,
            'message' => $queued.' message(s) queued. They go out over the next few minutes.',
        ]);
    }

    /**
     * Re-queues the rows that did not arrive.
     *
     * A failure is usually the mail server, not the message - the agent's first
     * batch bounced entirely because the send was configured against a
     * placeholder host - so the useful thing is to try again rather than
     * rebuild the plan and lose every decision already made.
     */
    public function retryFailed(Request $request, int $planId)
    {
        $this->assertManager($request);

        $plan = MarketingPlan::findOrFail($planId);

        $failed = $plan->items()
            ->where('status', MarketingPlanItem::STATUS_FAILED)
            ->get();

        if ($failed->isEmpty()) {
            return response()->json(['message' => 'Nothing failed on this plan.'], 422);
        }

        $campaign = Campaign::create([
            'name' => 'Agent retry - w/c '.$plan->week_starting->toDateString(),
            'channel' => 'email',
            'status' => 'sending',
            'recipient_count' => $failed->count(),
            'started_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        foreach ($failed as $item) {
            // Back to approved so the job will pick it up, and the previous
            // error is cleared rather than left to confuse the next reader.
            $item->update([
                'status' => MarketingPlanItem::STATUS_APPROVED,
                'blocked_reason' => null,
                'sent_communication_id' => null,
            ]);

            SendMarketingPlanItemJob::dispatch($item->id, $request->user()->id, $campaign->id);
        }

        $plan->update(['status' => MarketingPlan::STATUS_SENDING]);

        MarketingPlanEvent::record(
            $plan->id,
            MarketingPlanEvent::QUEUED,
            'Retried '.$failed->count().' message(s) that did not arrive.',
            null,
            $request->user()->id,
            ['count' => $failed->count()],
        );

        return response()->json([
            'queued' => $failed->count(),
            'message' => $failed->count().' message(s) queued again.',
        ]);
    }

    /** @return array<string, int|string> */
    private function limits(): array
    {
        return [
            'weekly_cap' => MarketingGuardrails::WEEKLY_RECIPIENT_CAP,
            'min_days_between_messages' => MarketingGuardrails::MIN_DAYS_BETWEEN_MESSAGES,
            'enabled_channels' => MarketingGuardrails::ENABLED_CHANNELS,
        ];
    }
}
