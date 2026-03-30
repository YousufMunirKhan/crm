<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    use HasAuditLog;

    const APPOINTMENT_STATUS_PENDING = 'pending';
    const APPOINTMENT_STATUS_COMPLETED = 'completed';
    const APPOINTMENT_STATUS_CANCELLED = 'cancelled';
    const APPOINTMENT_STATUS_NO_SHOW = 'no_show';
    const APPOINTMENT_STATUS_RESCHEDULED = 'rescheduled';

    protected $fillable = [
        'lead_id',
        'converted_to_lead_id',
        'user_id',
        'assigned_user_id',
        'type',
        'description',
        'meta',
        'remind_at',
        'appointment_status',
        'outcome_notes',
    ];

    protected $casts = [
        'meta' => 'array',
        'remind_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    public function convertedToLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'converted_to_lead_id');
    }
}


