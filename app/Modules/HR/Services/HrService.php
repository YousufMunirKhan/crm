<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\AttendanceSession;
use App\Modules\HR\Models\Salary;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HrService
{
    /**
     * Who hears about somebody clocking in or out.
     *
     * Managers are deliberately not on this list. Widening it is a one-line
     * change, but it is not a neutral one - a notification that arrives for
     * every clock-in of every day stops being read, and then the one that
     * mattered is not read either.
     */
    private const ATTENDANCE_NOTIFY_ROLES = ['Admin', 'System Admin'];

    public function checkIn(int $userId, array $proof = []): Attendance
    {
        $today = Attendance::workingDate();

        $attendance = DB::transaction(function () use ($userId, $today, $proof) {
            // Not firstOrCreate: `date` is cast to a date, so it is stored as a
            // midnight timestamp while the lookup value is a bare Y-m-d string.
            // firstOrCreate never matched the existing row and went straight to
            // inserting a duplicate.
            $attendance = Attendance::where('user_id', $userId)->whereDate('date', $today)->first()
                ?? Attendance::create(['user_id' => $userId, 'date' => $today]);

            if ($attendance->sessions()->open()->exists()) {
                throw new \Exception('You are already clocked in.');
            }

            $used = $attendance->sessions()->count();
            $limit = max(1, (int) config('attendance.max_sessions_per_day', 6));

            // A cap rather than no cap: a button pressed by accident should not
            // be able to fill the day with sessions.
            if ($used >= $limit) {
                throw new \Exception("You have already clocked in {$limit} times today.");
            }

            AttendanceSession::create([
                'attendance_id' => $attendance->id,
                'user_id' => $userId,
                'sequence' => $used + 1,
                'check_in_at' => now(),
                'check_in_photo_path' => $proof['photo_path'] ?? null,
                'check_in_latitude' => $proof['latitude'] ?? null,
                'check_in_longitude' => $proof['longitude'] ?? null,
                'check_in_location_name' => $proof['location_name'] ?? null,
                'check_in_location_accuracy' => $proof['accuracy'] ?? null,
                'check_in_location_source' => $proof['source'] ?? null,
            ]);

            return $this->rollUpDay($attendance);
        });

        $this->notifyAdminsOfClock($attendance, 'check_in');

        return $attendance;
    }

    public function checkOut(int $userId, array $proof = []): Attendance
    {
        $today = Attendance::workingDate();

        // whereDate rather than a plain equality: the column is cast to a date,
        // and on an engine without a real date type that writes a midnight
        // timestamp, which never equals a bare Y-m-d string.
        $attendance = DB::transaction(function () use ($userId, $today, $proof) {
            $attendance = Attendance::where('user_id', $userId)
                ->whereDate('date', $today)
                ->first();

            $session = $attendance?->sessions()->open()->orderByDesc('sequence')->first();

            if (! $session) {
                throw new \Exception('You are not clocked in.');
            }

            $session->update([
                'check_out_at' => now(),
                'check_out_photo_path' => $proof['photo_path'] ?? null,
                'check_out_latitude' => $proof['latitude'] ?? null,
                'check_out_longitude' => $proof['longitude'] ?? null,
                'check_out_location_name' => $proof['location_name'] ?? null,
                'check_out_location_accuracy' => $proof['accuracy'] ?? null,
                'check_out_location_source' => $proof['source'] ?? null,
            ]);

            return $this->rollUpDay($attendance);
        });

        $this->notifyAdminsOfClock($attendance, 'check_out');

        return $attendance;
    }

    /**
     * Rewrite the day row from its sessions.
     *
     * The day row is what every report on this system reads, and it keeps the
     * shape it always had: first clock-in, last clock-out, hours, and the proof
     * from each end. Nothing that reads it needed changing.
     *
     * Two decisions worth naming.
     *
     * **check_out_at stays null while a session is open.** Everything that asks
     * who is working treats a missing clock-out as "still here", and somebody on
     * their lunch break has not finished for the day. Only when nobody is
     * clocked in does the day get a finish time.
     *
     * **Hours run from the first clock-in to the last clock-out**, breaks
     * included, because that is how this business counts them. It is not the
     * only defensible answer - the alternative is to add the sessions up and
     * leave the gaps out - so it is written here rather than assumed.
     */
    private function rollUpDay(Attendance $attendance): Attendance
    {
        $sessions = $attendance->sessions()->get();

        $first = $sessions->first();
        $closed = $sessions->filter(fn (AttendanceSession $s) => $s->check_out_at !== null);
        $last = $closed->last();
        $stillIn = $sessions->contains(
            fn (AttendanceSession $s) => $s->check_out_at === null && $s->auto_closed_at === null
        );

        $attendance->fill([
            'check_in_at' => $first?->check_in_at,
            'check_in_photo_path' => $first?->check_in_photo_path,
            'check_in_latitude' => $first?->check_in_latitude,
            'check_in_longitude' => $first?->check_in_longitude,
            'check_in_location_name' => $first?->check_in_location_name,
            'check_in_location_accuracy' => $first?->check_in_location_accuracy,
            'check_in_location_captured_at' => $first?->check_in_at,
            'check_in_location_source' => $first?->check_in_location_source,

            'check_out_at' => $stillIn ? null : $last?->check_out_at,
            'check_out_photo_path' => $stillIn ? null : $last?->check_out_photo_path,
            'check_out_latitude' => $stillIn ? null : $last?->check_out_latitude,
            'check_out_longitude' => $stillIn ? null : $last?->check_out_longitude,
            'check_out_location_name' => $stillIn ? null : $last?->check_out_location_name,
            'check_out_location_accuracy' => $stillIn ? null : $last?->check_out_location_accuracy,
            'check_out_location_captured_at' => $stillIn ? null : $last?->check_out_at,
            'check_out_location_source' => $stillIn ? null : $last?->check_out_location_source,

            'sessions_count' => $sessions->count(),
            'breaks_count' => max(0, $sessions->count() - 1),
        ]);

        $attendance->work_hours = (! $stillIn && $first?->check_in_at && $last?->check_out_at)
            ? round($first->check_in_at->diffInMinutes($last->check_out_at, true) / 60, 2)
            : 0;

        $attendance->save();

        return $attendance;
    }

    /**
     * Put a clock-in or clock-out on the admins' notification bell.
     *
     * This runs inside the clock-in request, so it must not be able to stop one.
     * Somebody standing at a customer's door with their phone out is not going
     * to care that the notifications table is unhappy - the attendance is the
     * thing that has to be recorded, and a failure here is a line in the log.
     *
     * The person clocking in is skipped: an admin does not need telling about
     * their own shift.
     */
    private function notifyAdminsOfClock(Attendance $attendance, string $action): void
    {
        try {
            $checkingIn = $action === 'check_in';

            $staff = User::find($attendance->user_id);
            $when = $checkingIn ? $attendance->check_in_at : $attendance->check_out_at;
            $where = $checkingIn
                ? $attendance->check_in_location_name
                : $attendance->check_out_location_name;

            $title = ($staff?->name ?? 'Someone').' '.($checkingIn ? 'clocked in' : 'clocked out');

            $message = $title.' at '.$when->format('g:i A');

            if ($where) {
                $message .= ' - '.$where;
            }

            if (! $checkingIn && $attendance->work_hours !== null) {
                $message .= '. Worked '.number_format((float) $attendance->work_hours, 2).' hours';
            }

            $recipients = User::query()
                ->whereKeyNot($attendance->user_id)
                // is_active is nullable on older rows, and a null there means
                // nobody ever disabled them rather than that they are gone.
                ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
                ->whereHas('role', fn ($q) => $q->whereIn('name', self::ATTENDANCE_NOTIFY_ROLES))
                ->pluck('id');

            foreach ($recipients as $recipientId) {
                Notification::notifyUser(
                    $recipientId,
                    'attendance.'.$action,
                    $title,
                    $message,
                    [
                        'attendance_id' => $attendance->id,
                        'user_id' => $attendance->user_id,
                        'user_name' => $staff?->name,
                        'action' => $action,
                        'at' => $when?->toIso8601String(),
                        'location_name' => $where,
                    ],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Could not notify admins of an attendance clock: '.$e->getMessage());
        }
    }

    public function createSalary(int $userId, string $month, float $baseSalary, float $allowances = 0, float $deductions = 0): Salary
    {
        $netSalary = $baseSalary + $allowances - $deductions;

        return Salary::updateOrCreate(
            [
                'user_id' => $userId,
                'month' => $month,
            ],
            [
                'base_salary' => $baseSalary,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
            ]
        );
    }
}


