<?php

namespace App\Services;

use App\Mail\AutomatedEmail;
use App\Models\User;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Moves one person's pipeline to one or more others, and tells each of them
 * what they got.
 *
 * People leave and their leads stay exactly where they were - assigned to an
 * account that no longer logs in, and so missing from every list that starts
 * from an active user. The email is half the point: a book that lands silently
 * in somebody's account is a book nobody goes through.
 */
class LeadHandoverService
{
    /** Open stages, nearest to a signature first. */
    public const OPEN_STAGES = ['quotation', 'hot_lead', 'follow_up', 'lead'];

    public function __construct(private AutomatedEmailSender $sender) {}

    /**
     * What this person is still holding, by stage.
     *
     * @return array{total: int, by_stage: array<string, int>}
     */
    public function openWorkFor(User $user): array
    {
        $byStage = Lead::query()
            ->where('assigned_to', $user->id)
            ->whereIn('stage', self::OPEN_STAGES)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->map(fn ($n) => (int) $n)
            ->all();

        // Ordered by how close each stage is to closing, so the screen reads
        // the same way the handover email does.
        $ordered = [];
        foreach (self::OPEN_STAGES as $stage) {
            if (! empty($byStage[$stage])) {
                $ordered[$stage] = $byStage[$stage];
            }
        }

        return ['total' => array_sum($ordered), 'by_stage' => $ordered];
    }

    /**
     * Split one person's leads between the people taking them on.
     *
     * Dealt round-robin down a list ordered closest-to-closing, rather than cut
     * into blocks: a straight split by count would hand one person every
     * quotation and the next every cold lead.
     *
     * @param  Collection<int, User>  $recipients
     * @param  string[]|null  $stages
     * @return array<int, array{user: User, count: int, emailed: bool}>
     */
    public function handOver(User $from, Collection $recipients, ?array $stages = null): array
    {
        $recipients = $recipients->values();

        if ($recipients->isEmpty()) {
            return [];
        }

        $leads = Lead::query()
            ->where('assigned_to', $from->id)
            ->whereIn('stage', $stages ?: self::OPEN_STAGES)
            ->with(['customer', 'items.product'])
            ->get()
            ->sortBy([
                fn (Lead $a, Lead $b) => $this->closeness($a) <=> $this->closeness($b),
                fn (Lead $a, Lead $b) => (float) $b->pipeline_value <=> (float) $a->pipeline_value,
            ])
            ->values();

        if ($leads->isEmpty()) {
            return [];
        }

        $shares = [];

        foreach ($leads as $index => $lead) {
            $shares[$index % $recipients->count()][] = $lead;
        }

        $result = [];

        foreach ($shares as $position => $theirs) {
            $recipient = $recipients[$position];

            Lead::whereIn('id', collect($theirs)->pluck('id'))->update(['assigned_to' => $recipient->id]);

            $result[] = [
                'user' => $recipient,
                'count' => count($theirs),
                'emailed' => $this->tell($from, $recipient, collect($theirs)),
            ];
        }

        return $result;
    }

    /** @param  Collection<int, Lead>  $leads */
    private function tell(User $from, User $to, Collection $leads): bool
    {
        $rows = $leads->map(fn (Lead $lead) => [
            'id' => $lead->id,
            'customer' => $lead->customer?->business_name ?: ($lead->customer?->name ?: 'Customer'),
            'contact' => $lead->customer?->business_name ? $lead->customer?->name : null,
            'phone' => $lead->customer?->phone,
            'stage' => ucwords(str_replace('_', ' ', (string) $lead->stage)),
            'value' => (float) ($lead->pipeline_value ?? 0),
            'source' => $lead->source,
            'follow_up' => $lead->next_follow_up_at?->setTimezone(config('app.display_timezone'))->format('j M Y'),
            'products' => $lead->items->map(fn ($item) => $item->product?->name)->filter()->unique()->values()->all(),
            'url' => rtrim((string) config('app.url'), '/').'/leads/'.$lead->id,
        ])->all();

        $mail = new AutomatedEmail(
            mailSubject: count($rows).' of '.$from->name."'s leads are now yours",
            mailView: 'emails.automated.lead-handover',
            mailData: [
                'recipientName' => $to->name,
                'fromName' => $from->name,
                'rows' => $rows,
                'total' => count($rows),
                'totalValue' => array_sum(array_column($rows, 'value')),
            ],
        );

        return $this->sender->send(
            'lead-handover:'.$from->id.':'.$to->id.':'.now()->format('YmdHis'),
            $to->email,
            $mail,
        );
    }

    /**
     * Lower is nearer to a signature.
     *
     * Compared against false rather than treated as truthy: 'quotation' is
     * index 0, and `?: 99` sent the one closest to closing to the bottom.
     */
    private function closeness(Lead $lead): int
    {
        $order = array_search($lead->stage, self::OPEN_STAGES, true);

        return $order === false ? 99 : $order;
    }
}
