<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'user_id',
        'month',
        'base_salary',
        'allowances',
        'house_allowance',
        'medical_allowance',
        'other_allowance',
        'deductions',
        'tax',
        'loan_deduction',
        'other_deduction',
        'net_salary',
        'currency',
        'bonuses',
        'deductions_detail',
        'attendance_days',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'house_allowance' => 'decimal:2',
        'medical_allowance' => 'decimal:2',
        'other_allowance' => 'decimal:2',
        'deductions' => 'decimal:2',
        'tax' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'bonuses' => 'array',
        'deductions_detail' => 'array',
        'attendance_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}


