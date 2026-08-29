<?php

namespace App\Console\Commands;

use App\Modules\Marketing\Models\MarketingPlan;
use App\Modules\Marketing\Services\MarketingPlannerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Builds next week's draft plan. Sends nothing - a person still has to approve
 * it. Scheduled for Monday morning so the plan is waiting when someone starts.
 */
class GenerateMarketingPlan extends Command
{
    protected $signature = 'marketing:plan
        {--week= : Week starting date (defaults to this Monday)}
        {--replace : Discard an existing draft for that week and rebuild it}';

    protected $description = 'Generate the weekly marketing plan for review';

    public function handle(MarketingPlannerService $planner): int
    {
        $week = $this->option('week')
            ? Carbon::parse($this->option('week'))->startOfDay()
            : now()->startOfWeek();

        $existing = MarketingPlan::forWeek($week->toDateString())->first();

        if ($existing && ! $this->option('replace')) {
            $this->warn("A plan for week {$week->toDateString()} already exists (#{$existing->id}, {$existing->status}).");
            $this->line('Pass --replace to rebuild it.');

            return self::SUCCESS;
        }

        if ($existing) {
            if ($existing->status !== MarketingPlan::STATUS_DRAFT) {
                $this->error("Plan #{$existing->id} is {$existing->status}, not a draft. Refusing to replace it.");

                return self::FAILURE;
            }
            $existing->delete();
            $this->line("Replaced draft #{$existing->id}.");
        }

        try {
            ['plan' => $plan, 'notes' => $notes] = $planner->generate($week);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $s = $plan->rail_summary ?? [];

        $this->info("Plan #{$plan->id} for week starting {$week->toDateString()}");
        $this->table(
            ['', 'Count'],
            [
                ['Contacts considered', $s['candidates'] ?? 0],
                ['Proposed by the model', $s['proposed'] ?? 0],
                ['Ready to send', $s['sendable'] ?? 0],
                ['Blocked by the rules', $s['blocked'] ?? 0],
            ],
        );

        foreach (($s['blocked_reasons'] ?? []) as $reason => $count) {
            $this->line("  {$count} x {$reason}");
        }

        foreach ($notes as $note) {
            $this->warn('  '.$note);
        }

        $this->line('Nothing has been sent. Review and approve it in Marketing -> Agent.');

        return self::SUCCESS;
    }
}
