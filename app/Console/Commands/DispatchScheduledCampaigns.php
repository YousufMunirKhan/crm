<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

/**
 * Starts campaigns whose scheduled time has arrived.
 *
 * Every send previously required a human holding a browser tab open, which is
 * also why nothing could be scheduled for a sensible sending window.
 */
class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due {--dry-run}';

    protected $description = 'Start any scheduled campaign whose time has come';

    public function handle(): int
    {
        $due = Campaign::due()->get();

        if ($due->isEmpty()) {
            $this->info('No campaigns due.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Campaign', 'Channel', 'Scheduled'],
                $due->map(fn (Campaign $c) => [
                    $c->name,
                    $c->channel,
                    optional($c->scheduled_at)->format('Y-m-d H:i'),
                ])->all()
            );
            $this->warn($due->count().' campaign(s) would start.');

            return self::SUCCESS;
        }

        foreach ($due as $campaign) {
            // Claim it first so a slow run cannot start the same campaign twice.
            $campaign->markSending();

            $this->info("Started campaign: {$campaign->name} ({$campaign->channel})");
        }

        return self::SUCCESS;
    }
}
