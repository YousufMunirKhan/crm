<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Salary;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
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

        $attendance = Attendance::firstOrNew([
            'user_id' => $userId,
            'date' => $today,
        ]);

        if ($attendance->check_in_at) {
            throw new \Exception('Already checked in today');
        }

        $attendance->check_in_at = now();
        $attendance->fill([
            'check_in_photo_path' => $proof['photo_path'] ?? null,
            'check_in_latitude' => $proof['latitude'] ?? null,
            'check_in_longitude' => $proof['longitude'] ?? null,
            'check_in_location_name' => $proof['location_name'] ?? null,
            'check_in_location_accuracy' => $proof['accuracy'] ?? null,
            'check_in_location_captured_at' => $proof['captured_at'] ?? now(),
            'check_in_location_source' => $proof['source'] ?? null,
        ]);
        $attendance->save();

        $this->notifyAdminsOfClock($attendance, 'check_in');

        return $attendance;
    }

    public function checkOut(int $userId, array $proof = []): Attendance
    {
        $today = Attendance::workingDate();

        // whereDate rather than a plain equality: the column is cast to a date,
        // and on an engine without a real date type that writes a midnight
        // timestamp, which never equals a bare Y-m-d string.
        $attendance = Attendance::where('user_id', $userId)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_at) {
            throw new \Exception('No check-in found for today');
        }

        if ($attendance->check_out_at) {
            throw new \Exception('Already checked out today');
        }

        $attendance->check_out_at = now();
        $attendance->fill([
            'check_out_photo_path' => $proof['photo_path'] ?? null,
            'check_out_latitude' => $proof['latitude'] ?? null,
            'check_out_longitude' => $proof['longitude'] ?? null,
            'check_out_location_name' => $proof['location_name'] ?? null,
            'check_out_location_accuracy' => $proof['accuracy'] ?? null,
            'check_out_location_captured_at' => $proof['captured_at'] ?? now(),
            'check_out_location_source' => $proof['source'] ?? null,
        ]);
        $attendance->work_hours = $attendance->check_in_at->diffInHours($attendance->check_out_at, true);
        $attendance->save();

        $this->notifyAdminsOfClock($attendance, 'check_out');

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


