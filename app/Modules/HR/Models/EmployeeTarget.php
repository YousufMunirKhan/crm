<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}


