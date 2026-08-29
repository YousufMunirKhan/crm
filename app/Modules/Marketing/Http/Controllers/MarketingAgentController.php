<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Modules\Marketing\Jobs\SendMarketingPlanItemJob;
use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Models\MarketingPlanItem;
use App\Modules\Marketing\Services\MarketingGuardrails;
use App\Modules\Marketing\Services\MarketingPlannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        return response()->json([
            'data' => MarketingPlan::query()
                ->with(['generatedBy:id,name', 'approvedBy:id,name'])
                ->latest('week_starting')
                ->limit(20)
                ->get(),
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
            'limits' => $this->limits(),
        ]);
    }

    public function generate(Request $request, MarketingPlannerService $planner)
    {
        $this->assertManager($request);

        $week = $request->filled('week')
            ? \Illuminate\Support\Carbon::parse($request->week)->startOfDay()
            : now()->startOfWeek();

        $existing = MarketingPlan::forWeek($week->toDateString())->first();

        if ($existing && $existing->status !== MarketingPlan::STATUS_DRAFT) {
            return response()->json([
                'message' => "A plan for that week already exists and is {$existing->status}.",
            ], 422);
        }

        $existing?->delete();

        try {
            ['plan' => $plan] = $planner->generate($week, $request->user()->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

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

        $item->update($data);

        return response()->json(['item' => $item->fresh(['customer', 'emailTemplate'])]);
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

        $changed = $query->update(['status' => $data['status']]);

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

        return response()->json([
            'queued' => $queued,
            'message' => $queued.' message(s) queued. They go out over the next few minutes.',
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
