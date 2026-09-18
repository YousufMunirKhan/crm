<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\CRM\Models\Lead;
use App\Services\LeadHandoverService;
use Illuminate\Console\Command;

/**
 * Moves one person's leads to one or more others from the command line.
 *
 * The work itself lives in LeadHandoverService, because the same thing happens
 * from the Employees screen when somebody is marked inactive, and the two must
 * not drift into splitting a pipeline two different ways.
 */
class HandOverLeads extends Command
{
    protected $signature = 'crm:hand-over-leads
        {from : Name or id of the person leaving}
        {to* : Name or id of each person taking them on}
        {--stages= : Comma-separated stages to move, default every open one}
        {--dry-run : List what would move without moving it}';

    protected $description = 'Reassign somebody\'s leads and email the people taking them on';

    public function handle(LeadHandoverService $handover): int
    {
        $from = $this->findUser($this->argument('from'));

        if (! $from) {
            $this->error('Could not find '.$this->argument('from').'.');

            return self::FAILURE;
        }

        $recipients = collect($this->argument('to'))->map(fn ($n) => $this->findUser((string) $n))->filter();

        if ($recipients->isEmpty()) {
            $this->error('Could not find anybody to hand them to.');

            return self::FAILURE;
        }

        $stages = $this->option('stages')
            ? array_filter(array_map('trim', explode(',', (string) $this->option('stages'))))
            : null;

        if ($this->option('dry-run')) {
            $leads = Lead::where('assigned_to', $from->id)
                ->whereIn('stage', $stages ?: LeadHandoverService::OPEN_STAGES)
                ->with('customer')
                ->get();

            foreach ($leads as $lead) {
                $this->line(sprintf(
                    '  #%-5d %-32s %s',
                    $lead->id,
                    substr((string) ($lead->customer?->business_name ?: $lead->customer?->name ?: '-'), 0, 31),
                    $lead->stage
                ));
            }

            $this->info($leads->count().' lead(s) would be split between '.$recipients->pluck('name')->implode(', ').'.');

            return self::SUCCESS;
        }

        $result = $handover->handOver($from, $recipients, $stages);

        if ($result === []) {
            $this->info('Nothing to move.');

            return self::SUCCESS;
        }

        foreach ($result as $share) {
            $this->info(sprintf(
                '%s: %d lead(s), email %s',
                $share['user']->name,
                $share['count'],
                $share['emailed'] ? 'sent' : 'NOT sent'
            ));
        }

        return self::SUCCESS;
    }

    private function findUser(string $needle): ?User
    {
        return ctype_digit($needle)
            ? User::find((int) $needle)
            : User::where('name', $needle)->first();
    }
}
