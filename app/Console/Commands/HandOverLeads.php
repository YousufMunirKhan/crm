<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Models\User;
use App\Modules\CRM\Models\Lead;
use App\Services\AutomatedEmailSender;
use Illuminate\Console\Command;

/**
 * Moves one person's leads to another, and tells the receiver what they got.
 *
 * People leave, and their pipeline stays exactly where it was: assigned to
 * somebody who no longer logs in, invisible to every list that starts from an
 * active user. One person on this system left holding fourteen hot leads and
 * three quotations that nobody had looked at since.
 *
 * The email is the point as much as the move. A book that lands silently in
 * somebody's account is a book nobody goes through.
 */
class HandOverLeads extends Command
{
    protected $signature = 'crm:hand-over-leads
        {from : Name or id of the person leaving}
        {to : Name or id of the person taking them on}
        {--stages= : Comma-separated stages to move, default every open one}
        {--dry-run : List what would move without moving it}';

    protected $description = 'Reassign somebody\'s leads and email the person taking them on';

    public function handle(AutomatedEmailSender $sender): int
    {
        $from = $this->findUser($this->argument('from'));
        $to = $this->findUser($this->argument('to'));

        if (! $from || ! $to) {
            $this->error('Could not find one of them.');

            return self::FAILURE;
        }

        $stages = $this->option('stages')
            ? array_filter(array_map('trim', explode(',', (string) $this->option('stages'))))
            : ['follow_up', 'lead', 'hot_lead', 'quotation'];

        $leads = Lead::query()
            ->where('assigned_to', $from->id)
            ->whereIn('stage', $stages)
            ->with(['customer', 'items.product'])
            ->get()
            // Sorted here rather than in SQL: FIELD() is MySQL's and the tests
            // run on sqlite, and "closest to closing" is a business order that
            // reads better written out than buried in a query.
            ->sortBy([
                fn (Lead $a, Lead $b) => $this->closeness($a) <=> $this->closeness($b),
                fn (Lead $a, Lead $b) => (float) $b->pipeline_value <=> (float) $a->pipeline_value,
            ])
            ->values();

        if ($leads->isEmpty()) {
            $this->info('Nothing to move.');

            return self::SUCCESS;
        }

        $rows = $leads->map(fn (Lead $lead) => [
            'id' => $lead->id,
            'customer' => $lead->customer?->business_name ?: ($lead->customer?->name ?: 'Customer'),
            'contact' => $lead->customer?->business_name ? $lead->customer?->name : null,
            'phone' => $lead->customer?->phone,
            'stage' => ucwords(str_replace('_', ' ', (string) $lead->stage)),
            'value' => (float) ($lead->pipeline_value ?? 0),
            'source' => $lead->source,
            'follow_up' => $lead->next_follow_up_at?->setTimezone(config('app.display_timezone'))->format('j M Y'),
            'products' => $lead->items
                ->map(fn ($item) => $item->product?->name)
                ->filter()->unique()->values()->all(),
            'url' => rtrim((string) config('app.url'), '/').'/leads/'.$lead->id,
        ])->all();

        if ($this->option('dry-run')) {
            foreach ($rows as $row) {
                $this->line(sprintf('  #%-5d %-32s %-12s %s', $row['id'], substr($row['customer'], 0, 31), $row['stage'], $row['follow_up'] ?: ''));
            }
            $this->info(count($rows).' lead(s) would move to '.$to->name.'.');

            return self::SUCCESS;
        }

        Lead::whereIn('id', $leads->pluck('id'))->update(['assigned_to' => $to->id]);

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

        $sent = $sender->send(
            'lead-handover:'.$from->id.':'.$to->id.':'.now()->format('YmdHis'),
            $to->email,
            $mail,
        );

        $this->info('Moved '.count($rows).' lead(s) to '.$to->name.'. Email '.($sent ? 'sent' : 'NOT sent').'.');

        return self::SUCCESS;
    }

    /**
     * Lower is nearer to a signature.
     *
     * Compared against false, not treated as truthy: 'quotation' is index 0,
     * and `?: 99` sent the one closest to closing to the bottom of the list.
     */
    private function closeness(Lead $lead): int
    {
        $order = array_search($lead->stage, ['quotation', 'hot_lead', 'follow_up', 'lead'], true);

        return $order === false ? 99 : $order;
    }

    private function findUser(string $needle): ?User
    {
        return ctype_digit($needle)
            ? User::find((int) $needle)
            : User::where('name', $needle)->first();
    }
}
