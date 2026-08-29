<?php

namespace App\Modules\Marketing\Services;

use App\Models\EmailTemplate;
use App\Models\MessageTemplate;
use App\Models\SentCommunication;
use App\Modules\CRM\Models\Customer;
use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Models\MarketingPlanItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds the week's marketing plan.
 *
 * The model is sent numbered facts and nothing else - no names, no email
 * addresses, no phone numbers, and no business names either, since a trading
 * name identifies a person as surely as their own does. It answers with ids,
 * and this class turns ids back into people locally.
 *
 * Everything it says is then checked: an id it invented, a purpose that is not
 * a real template, a channel the contact has not consented to. The model
 * proposes; the guardrails dispose.
 */
class MarketingPlannerService
{
    public function __construct(
        private MarketingGuardrails $rails,
        private \App\Services\ClaudeContactExtractionService $claude,
    ) {}

    /**
     * @return array{plan: MarketingPlan, notes: array<int, string>}
     */
    public function generate(Carbon $weekStarting, ?int $userId = null): array
    {
        $notes = [];
        $candidates = $this->candidates();

        if ($candidates->isEmpty()) {
            $notes[] = 'No contacts were eligible this week.';
        }

        $purposes = $this->availablePurposes();

        if ($purposes === []) {
            throw new \RuntimeException('No marketing agent templates are installed. Run marketing:install-templates.');
        }

        $suggestions = $candidates->isEmpty()
            ? []
            : $this->ask($candidates, $purposes, $notes);

        return DB::transaction(function () use ($weekStarting, $userId, $suggestions, $candidates, $purposes, &$notes) {
            $plan = MarketingPlan::create([
                'week_starting' => $weekStarting->toDateString(),
                'status' => MarketingPlan::STATUS_DRAFT,
                'model' => $this->claude->model(),
                'generated_at' => now(),
                'generated_by' => $userId,
            ]);

            $byId = $candidates->keyBy('id');
            $counts = ['proposed' => 0, 'blocked' => 0];
            $blockedReasons = [];
            $allowed = 0;

            foreach ($suggestions as $s) {
                $customerId = (int) ($s['id'] ?? 0);
                $customer = $byId[$customerId] ?? null;

                // An id we never sent, or one already used in this plan.
                if ($customer === null) {
                    continue;
                }
                $byId->forget($customerId);

                $purpose = (string) ($s['purpose'] ?? '');
                if (! isset($purposes[$purpose])) {
                    $notes[] = "Ignored an unknown purpose: {$purpose}";
                    continue;
                }

                $record = Customer::find($customerId);
                if ($record === null) {
                    continue;
                }

                $best = $this->rails->bestChannel($record, $s['channel'] ?? null);
                $channel = $best['channel'];
                $counts['proposed']++;

                if ($channel === null) {
                    $reason = $best['reasons']['email'] ?? 'Not reachable on any channel';
                    $blockedReasons[$reason] = ($blockedReasons[$reason] ?? 0) + 1;
                    $counts['blocked']++;

                    $this->storeItem($plan, $record, $purpose, 'email', $s, MarketingPlanItem::STATUS_BLOCKED, $reason, $purposes);

                    continue;
                }

                // The cap is a ceiling on what can actually go out, so it is
                // applied to sendable rows only - blocked rows cost nothing.
                if ($allowed >= MarketingGuardrails::WEEKLY_RECIPIENT_CAP) {
                    $reason = 'Weekly cap of '.MarketingGuardrails::WEEKLY_RECIPIENT_CAP.' reached';
                    $blockedReasons[$reason] = ($blockedReasons[$reason] ?? 0) + 1;

                    $this->storeItem($plan, $record, $purpose, $channel, $s, MarketingPlanItem::STATUS_BLOCKED, $reason, $purposes);

                    continue;
                }

                $allowed++;
                $this->storeItem($plan, $record, $purpose, $channel, $s, MarketingPlanItem::STATUS_PENDING, null, $purposes);
            }

            $plan->update([
                'item_count' => $plan->items()->count(),
                'rail_summary' => [
                    'candidates' => $candidates->count(),
                    'proposed' => $counts['proposed'],
                    'sendable' => $allowed,
                    'blocked' => $counts['blocked'],
                    'blocked_reasons' => $blockedReasons,
                ],
                'notes' => $notes === [] ? null : implode("\n", $notes),
            ]);

            return ['plan' => $plan->fresh('items'), 'notes' => $notes];
        });
    }

    private function storeItem(
        MarketingPlan $plan,
        Customer $customer,
        string $purpose,
        string $channel,
        array $suggestion,
        string $status,
        ?string $blockedReason,
        array $purposes,
    ): void {
        MarketingPlanItem::create([
            'marketing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'lead_id' => $customer->leads()->latest('id')->value('id'),
            'channel' => $channel,
            'purpose' => $purpose,
            'email_template_id' => $purposes[$purpose]['email_id'] ?? null,
            'message_template_id' => $purposes[$purpose]['sms_id'] ?? null,
            'reason' => Str::limit(trim((string) ($suggestion['reason'] ?? '')), 400) ?: null,
            'priority' => max(1, min(5, (int) ($suggestion['priority'] ?? 3))),
            'status' => $status,
            'blocked_reason' => $blockedReason,
        ]);
    }

    /**
     * Contacts worth considering: not deleted, reachable on something, and not
     * messaged inside the frequency window. Filtering here keeps the prompt -
     * and the bill - proportional to the work.
     *
     * @return Collection<int, object>
     */
    public function candidates(): Collection
    {
        $recentlyMessaged = SentCommunication::query()
            ->whereNotNull('campaign_id')
            ->where('sent_at', '>=', now()->subDays(MarketingGuardrails::MIN_DAYS_BETWEEN_MESSAGES))
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->all();

        // Only channels that can actually deliver are worth planning for, so a
        // contact with no email is not a candidate at all while email is the
        // only one switched on. They were being planned, then blocked, which
        // filled the review screen with rows nobody could act on - 219 of 579
        // on live - and cost tokens describing people we cannot write to.
        $reachable = MarketingGuardrails::ENABLED_CHANNELS;

        return Customer::query()
            ->with(['leads.items.product', 'leads.product'])
            ->where(function ($q) use ($reachable) {
                if (in_array('email', $reachable, true)) {
                    $q->orWhere(fn ($e) => $e->whereNotNull('email')->where('email', '!=', ''));
                }
                if (array_intersect(['sms', 'whatsapp'], $reachable)) {
                    $q->orWhere(fn ($e) => $e->whereNotNull('phone')->where('phone', '!=', ''));
                }
            })
            ->when($recentlyMessaged !== [], fn ($q) => $q->whereNotIn('id', $recentlyMessaged))
            ->get()
            ->map(fn (Customer $c) => $this->facts($c));
    }

    /**
     * The anonymised view of a contact. Everything here is a fact about the
     * relationship; nothing here identifies the person.
     */
    private function facts(Customer $c): object
    {
        $owned = [];
        $pipeline = [];
        $lastActivity = null;
        $stage = null;

        foreach ($c->leads as $lead) {
            $stage ??= $lead->stage;
            $lastActivity = max($lastActivity, (string) $lead->updated_at);

            foreach ($lead->items as $item) {
                $name = $item->product?->category ?: $item->product?->name;
                if (! $name) {
                    continue;
                }
                if ($item->status === 'won') {
                    $owned[$name] = true;
                } else {
                    $pipeline[$name] = true;
                }
            }
        }

        $allCategories = ['ePOS', 'Website', 'Card Terminal', 'Business Funding', 'ePOS Bundle'];

        return (object) [
            'id' => $c->id,
            'type' => $c->type,
            'stage' => $stage,
            'days_since_contact' => $lastActivity
                ? (int) Carbon::parse($lastActivity)->diffInDays(now())
                : null,
            'days_since_created' => (int) $c->created_at?->diffInDays(now()),
            'owns' => array_keys($owned),
            'in_pipeline' => array_keys($pipeline),
            'not_owns' => array_values(array_diff($allCategories, array_keys($owned))),
            'city' => $c->city,
            'source' => $c->source,
            'licence_days_left' => $c->lic_days,
            'has_birthday_today' => $c->birthday
                ? Carbon::parse($c->birthday)->isBirthday(now())
                : false,
        ];
    }

    /** @return array<string, array{email_id: ?int, sms_id: ?int, when: ?string}> */
    public function availablePurposes(): array
    {
        $emails = EmailTemplate::whereNotNull('purpose')->where('is_active', true)->get()->keyBy('purpose');
        $sms = MessageTemplate::whereNotNull('purpose')->where('is_active', true)->get()->keyBy('purpose');

        $out = [];

        foreach ($emails as $purpose => $template) {
            $out[$purpose] = [
                'email_id' => $template->id,
                'sms_id' => $sms[$purpose]->id ?? null,
                'when' => $template->description,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, object>  $candidates
     * @param  array<string, array{when: ?string}>  $purposes
     * @return array<int, array<string, mixed>>
     */
    private function ask(Collection $candidates, array $purposes, array &$notes): array
    {
        if (! $this->claude->isConfigured()) {
            $notes[] = 'No Anthropic API key configured - no plan was generated.';

            return [];
        }

        $menu = collect($purposes)
            ->map(fn ($p, $key) => "- {$key}: ".($p['when'] ?: 'no description'))
            ->implode("\n");

        $prompt = "You are planning one week of outbound marketing for a UK company that sells "
            ."EPOS systems, card terminals, online ordering websites, bundles and business funding "
            ."to small merchants (takeaways, grocers, retail).\n\n"
            ."Below is a JSON array of contacts. They are identified only by a number. Each one "
            ."shows what they already own, what they do not, how long since anyone spoke to them, "
            ."and whether they are an existing customer or a prospect.\n\n"
            ."Choose the contacts worth writing to this week and pick ONE reason for each, from "
            ."this list only:\n{$menu}\n\n"
            ."Rules:\n"
            ."- Return ONLY a JSON array. No markdown fences, no commentary.\n"
            ."- Each element: {\"id\": <number>, \"purpose\": \"<key from the list>\", "
            ."\"channel\": \"email\"|\"sms\", \"priority\": 1-5, \"reason\": \"<one short sentence, "
            ."plain English, addressed to a colleague>\"}\n"
            ."- Only use ids that appear in the input.\n"
            ."- Return AT MOST ".(MarketingGuardrails::WEEKLY_RECIPIENT_CAP + 10)." elements, "
            ."ordered best first. Only that many can be sent, so spend the effort choosing "
            ."rather than listing. Returning fewer is correct and expected; do not pad.\n"
            ."- Never suggest a purpose that contradicts the facts: no epos-upsell for someone "
            ."who already owns ePOS, no licence-renewal without licence_days_left.\n"
            ."- Prefer email. Suggest sms only when the message is genuinely time-sensitive.\n"
            ."- reason must say why THIS contact THIS week, referencing their facts.\n\n"
            ."CONTACTS:\n"
            .$candidates->take(400)->toJson();

        try {
            // The extraction service's 60s is for one short page of text. This
            // reasons over hundreds of contacts and writes a line about each
            // one it picks, so it gets its own budget.
            $response = Http::timeout((int) config('anthropic.planner_timeout', 240))
                ->withHeaders([
                    'x-api-key' => $this->claude->apiKey(),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->claude->model(),
                    'max_tokens' => 16000,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (\Throwable $e) {
            Log::error('Marketing planner HTTP failed', ['message' => $e->getMessage()]);
            $notes[] = 'Could not reach the planning model: '.$e->getMessage();

            return [];
        }

        if (! $response->successful()) {
            Log::error('Marketing planner API error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);
            $notes[] = 'The planning model returned an error ('.$response->status().').';

            return [];
        }

        $text = collect($response->json('content') ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $parsed = $this->parseJsonArray($text);

        if ($parsed === null) {
            Log::error('Marketing planner returned unparseable output', [
                'head' => Str::limit($text, 400),
                'tail' => Str::substr($text, -300),
                'length' => strlen($text),
            ]);
            $notes[] = 'The planning model did not return usable JSON.';

            return [];
        }

        return $parsed;
    }

    /** Tolerates a stray fence or a sentence wrapped around the array. */
    private function parseJsonArray(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text);

        $start = strpos($text, '[');

        if ($start === false) {
            return null;
        }

        $end = strrpos($text, ']');

        if ($end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Truncated output: salvage the objects that did arrive complete rather
        // than discarding a whole week's plan over one missing bracket.
        $salvaged = [];

        if (preg_match_all('/\\{[^{}]*\\}/', substr($text, $start), $matches)) {
            foreach ($matches[0] as $chunk) {
                $object = json_decode($chunk, true);

                if (is_array($object) && isset($object['id'])) {
                    $salvaged[] = $object;
                }
            }
        }

        return $salvaged === [] ? null : $salvaged;
    }
}
