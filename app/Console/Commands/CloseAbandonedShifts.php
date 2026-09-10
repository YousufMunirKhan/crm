<?php

namespace App\Console\Commands;

use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\AttendanceSession;
use Illuminate\Console\Command;

/**
 * Closes shifts nobody ever clocked out of.
 *
 * A quarter of every attendance row on this system is sitting open, the oldest
 * since February, and the count grows every day. Those rows are read as "still
 * on shift" by anything that asks who is working, and they never get an hours
 * figure, so the attendance report is missing a quarter of its days.
 *
 * What this deliberately does not do is write a finish time. Setting
 * check_out_at to the clock-in plus eight hours would make every report balance
 * and every one of those numbers would be invented, on the record that decides
 * what somebody is paid. The shift stops being open. The hours stay unknown,
 * because they are unknown, and a report that says so is worth more than one
 * that quietly makes them up.
 */
class CloseAbandonedShifts extends Command
{
    protected $signature = 'crm:close-abandoned-shifts
                            {--hours= : Hours open before a shift counts as abandoned}
                            {--dry-run : Show what would be closed and change nothing}';

    protected $description = 'Mark shifts nobody clocked out of as abandoned';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? config('attendance.abandon_after_hours', 16));

        if ($hours < 1) {
            $this->error('Hours must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subHours($hours);

        // Sessions are where a shift is actually open now; the day row above
        // them is a summary. Closing the session and then rebuilding the day
        // from it keeps the two saying the same thing.
        $openSessions = AttendanceSession::query()
            ->open()
            ->where('check_in_at', '<', $cutoff)
            ->pluck('attendance_id');

        $abandoned = Attendance::query()
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereNull('auto_closed_at')
            ->where('check_in_at', '<', $cutoff)
            ->when($openSessions->isNotEmpty(), fn ($q) => $q->orWhereIn('id', $openSessions))
            ->orderBy('check_in_at')
            ->get();

        if ($abandoned->isEmpty()) {
            $this->info('Nothing open longer than '.$hours.' hours.');

            return self::SUCCESS;
        }

        $this->line($abandoned->count().' shift(s) open longer than '.$hours.' hours:');

        // The oldest few by name, so somebody running this by hand can see what
        // they are about to touch rather than a number on its own.
        foreach ($abandoned->take(5) as $shift) {
            $this->line(sprintf(
                '  %s  user #%d  in at %s  (%d days ago)',
                $shift->date?->format('Y-m-d') ?? '?',
                $shift->user_id,
                $shift->check_in_at->format('H:i'),
                (int) $shift->check_in_at->diffInDays(now()),
            ));
        }

        if ($abandoned->count() > 5) {
            $this->line('  ... and '.($abandoned->count() - 5).' more');
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run. Nothing was changed.');

            return self::SUCCESS;
        }

        $ids = $abandoned->pluck('id');

        AttendanceSession::query()
            ->open()
            ->whereIn('attendance_id', $ids)
            ->update(['auto_closed_at' => now()]);

        Attendance::query()
            ->whereIn('id', $ids)
            ->update(['auto_closed_at' => now()]);

        $this->info('Closed '.$abandoned->count().'. No finish time was written - those hours were never recorded.');

        return self::SUCCESS;
    }
}
