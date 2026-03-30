<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentLog extends Model
{
    protected $table = 'lead_assignment_logs';

    protected $fillable = [
        'lead_id',
        'previous_assigned_to',
        'new_assigned_to',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }

    public function newAssignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'new_assigned_to');
    }

    public function previousAssignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'previous_assigned_to');
    }
}
