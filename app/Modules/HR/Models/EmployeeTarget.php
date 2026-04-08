<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeTarget extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'user_id',
        'month',
        'target_appointments',
        'target_sales',
        'target_revenue',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmployeeTargetLine::class, 'employee_target_id')->orderBy('id');
    }
}


