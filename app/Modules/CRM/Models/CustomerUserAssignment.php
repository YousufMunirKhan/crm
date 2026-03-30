<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUserAssignment extends Model
{
    use HasAuditLog;

    protected $table = 'customer_user_assignments';

    protected $fillable = [
        'customer_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }
}

