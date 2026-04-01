<?php

namespace App\Modules\Ticket\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'ticket_number',
        'source',
        'pos_external_id',
        'pos_shop_name',
        'pos_telephone',
        'pos_address',
        'pos_computer_name',
        'pos_support_status',
        'pos_resolution_notes',
        'pos_submitted_at',
        'pos_sent_at',
        'customer_id',
        'created_by',
        'assigned_to',
        'subject',
        'description',
        'reference_url',
        'priority',
        'estimated_resolve_hours',
        'status',
        'sla_due_at',
        'resolved_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'pos_submitted_at' => 'datetime',
        'pos_sent_at' => 'datetime',
    ];

    public function isPosSupport(): bool
    {
        return $this->source === 'pos_support';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    /** All users assigned to this ticket (multi-assign). Legacy {@see $assigned_to} is kept as the first assignee for exports. */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'ticket_assignees')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}


