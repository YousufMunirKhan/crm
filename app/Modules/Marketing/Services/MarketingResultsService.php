<?php

namespace App\Modules\Marketing\Services;

use App\Models\CommunicationClick;
use App\Models\EmailUnsubscribe;
use App\Models\SentCommunication;
use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Models\MarketingPlanItem;
use Illuminate\Support\Collection;

/**
 * What happened to the messages a plan sent.
 *
 * Counted per purpose rather than per week, because the decision this data
 * exists to support is "which reason to write is working" - if ePOS upsell is
 * opened three times as often as funding, that changes the copy or drops the
 * purpose. A weekly total answers no question anyone asks.
 *
 * Two honesty rules are built in rather than left to whoever reads the screen:
 * bounces are excluded from the denominator, because an email that never
 * arrived cannot be "not opened"; and clicks are carried alongside opens,
 * because Apple Mail Privacy Protection opens mail on the recipient's behalf
 * and inflates every open rate in the industry.
 */
class MarketingResultsService
{
    /**
     * @return array<string, mixed>
     */
    public function forPlan(MarketingPlan $plan): array
    {
        $items = $plan->items()
            ->whereIn('status', [MarketingPlanItem::STATUS_SENT, MarketingPlanItem::STATUS_FAILED])
            ->get(['id', 'purpose', 'customer_id', 'status', 'sent_communication_id']);

        if ($items->isEmpty()) {
            return ['has_results' => false, 'purposes' => [], 'totals' => $this->emptyTotals()];
        }

        $commIds = $items->pluck('sent_communication_id')->filter()->all();

        $comms = SentCommunication::query()
            ->whereIn('id', $commIds)
            ->get(['id', 'status', 'opened_at', 'open_count', 'failure_category', 'recipient_email', 'sent_at'])
            ->keyBy('id');

        $clickedIds = $commIds === []
            ? collect()
            : CommunicationClick::whereIn('sent_communication_id', $commIds)
                ->distinct()
                ->pluck('sent_communication_id')
                ->flip();

        // Anyone who opted out after this plan went out. Attribution is by
        // timing, not by proof - somebody may have unsubscribed from an
        // unrelated email the same afternoon - so the screen calls it
        // "unsubscribed since", not "unsubscribed because of this".
        $sentAt = $plan->approved_at ?? $plan->generated_at;
        $unsubscribedSince = $sentAt
            ? EmailUnsubscribe::where('unsubscribed_at', '>=', $sentAt)->pluck('email')
                ->map(fn ($e) => mb_strtolower(trim($e)))->flip()
            : collect();

        $byPurpose = [];

        foreach ($items->groupBy('purpose') as $purpose => $rows) {
            $byPurpose[$purpose] = $this->summarise($rows, $comms, $clickedIds, $unsubscribedSince);
        }

        uasort($byPurpose, fn ($a, $b) => $b['delivered'] <=> $a['delivered']);

        return [
            'has_results' => true,
            'purposes' => $byPurpose,
            'totals' => $this->summarise($items, $comms, $clickedIds, $unsubscribedSince),
        ];
    }

    /**
     * @param  Collection<int, MarketingPlanItem>  $rows
     * @return array<string, mixed>
     */
    private function summarise(Collection $rows, Collection $comms, Collection $clickedIds, Collection $unsubscribed): array
    {
        $attempted = $rows->count();
        $bounced = 0;
        $delivered = 0;
        $opened = 0;
        $clicked = 0;
        $optedOut = 0;

        foreach ($rows as $row) {
            $comm = $row->sent_communication_id ? $comms->get($row->sent_communication_id) : null;

            // No record, or the send itself errored: it never arrived.
            if ($comm === null || $comm->status === 'failed' || $row->status === MarketingPlanItem::STATUS_FAILED) {
                $bounced++;

                continue;
            }

            $delivered++;

            if ($comm->opened_at !== null) {
                $opened++;
            }

            if ($clickedIds->has($comm->id)) {
                $clicked++;
            }

            $email = mb_strtolower(trim((string) $comm->recipient_email));

            if ($email !== '' && $unsubscribed->has($email)) {
                $optedOut++;
            }
        }

        return [
            'attempted' => $attempted,
            'delivered' => $delivered,
            'bounced' => $bounced,
            'opened' => $opened,
            'clicked' => $clicked,
            'unsubscribed' => $optedOut,
            // Denominator is delivered, never attempted. A bounce is not a
            // person who ignored you.
            'open_rate' => $this->rate($opened, $delivered),
            'click_rate' => $this->rate($clicked, $delivered),
            'unsubscribe_rate' => $this->rate($optedOut, $delivered),
        ];
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }

    /** @return array<string, mixed> */
    private function emptyTotals(): array
    {
        return [
            'attempted' => 0, 'delivered' => 0, 'bounced' => 0,
            'opened' => 0, 'clicked' => 0, 'unsubscribed' => 0,
            'open_rate' => null, 'click_rate' => null, 'unsubscribe_rate' => null,
        ];
    }

    /**
     * Every plan that has sent anything, newest first, so purposes can be
     * compared across weeks rather than judged on one send.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 12): array
    {
        return MarketingPlan::query()
            ->whereIn('status', [MarketingPlan::STATUS_SENDING, MarketingPlan::STATUS_SENT])
            ->latest('week_starting')
            ->limit($limit)
            ->get()
            ->map(fn (MarketingPlan $plan) => [
                'id' => $plan->id,
                'week_starting' => $plan->week_starting->toDateString(),
                'status' => $plan->status,
            ] + $this->forPlan($plan))
            ->all();
    }
}
