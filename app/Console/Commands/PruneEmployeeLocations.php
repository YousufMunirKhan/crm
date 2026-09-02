<?php

namespace App\Console\Commands;

use App\Modules\HR\Models\EmployeeLocation;
use Illuminate\Console\Command;

/**
 * Deletes location history once it is past its usefulness.
 *
 * Location is the most sensitive thing this system holds. Keeping it
 * indefinitely is both a liability and, under UK data protection rules, an
 * answer nobody wants to have to give: "how long do you keep it" cannot be
 * "forever" for staff movement data.
 *
 * Ninety days is the default because it covers a full quarter - long enough to
 * answer a question about a disputed visit or an expense claim, short enough
 * that a breach two years from now cannot expose where somebody was last
 * spring. Set LOCATION_RETENTION_DAYS to change it.
 *
 * The attendance record itself is untouched. Only the minute-by-minute trail
 * goes; who worked which day, and where they clocked in and out, remains.
 */
class PruneEmployeeLocations extends Command
{
    protected $signature = 'crm:prune-locations {--dry-run : Report what would go without deleting}';

    protected $description = 'Delete employee location history older than the retention period';

    public function handle(): int
    {
        $days = max(1, (int) config('hr.location_retention_days', 90));
        $cutoff = now()->subDays($days);

        $query = EmployeeLocation::where('recorded_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} location point(s) recorded before {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        // Chunked so a year's backlog cannot lock the table on the first run.
        $deleted = 0;

        do {
            $batch = EmployeeLocation::where('recorded_at', '<', $cutoff)->limit(2000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} location point(s) recorded before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
