<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Salary;
use App\Models\User;
use Carbon\Carbon;

class HrService
{
    public function checkIn(int $userId): Attendance
    {
        $today = now()->toDateString();

        $attendance = Attendance::firstOrNew([
            'user_id' => $userId,
            'date' => $today,
        ]);

        if ($attendance->check_in_at) {
            throw new \Exception('Already checked in today');
        }

        $attendance->check_in_at = now();
        $attendance->save();

        return $attendance;
    }

    public function checkOut(int $userId): Attendance
    {
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_at) {
            throw new \Exception('No check-in found for today');
        }

        if ($attendance->check_out_at) {
            throw new \Exception('Already checked out today');
        }

        $attendance->check_out_at = now();
        $attendance->work_hours = $attendance->check_in_at->diffInHours($attendance->check_out_at, true);
        $attendance->save();

        return $attendance;
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


