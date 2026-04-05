<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
    use HasAuditLog, HasApiTokens;

    public const TYPE_PROSPECT = 'prospect';
    public const TYPE_CUSTOMER = 'customer';

    protected $fillable = [
        'type',
        'name',
        'business_name',
        'owner_name',
        'contact_person_2_name',
        'contact_person_2_phone',
        'phone',
        'email',
        'whatsapp_number',
        'email_secondary',
        'sms_number',
        'address',
        'postcode',
        'city',
        'vat_number',
        'notes',
        'source',
        'anydesk_rustdesk',
        'passwords',
        'epos_type',
        'lic_days',
        'birthday',
        'category',
        'portal_password',
        'latitude',
        'longitude',
        'created_by',
    ];

    protected $hidden = ['portal_password'];

    protected $attributes = [
        'type' => self::TYPE_PROSPECT,
    ];

    protected $casts = [
        'birthday' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Invoice\Models\Invoice::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(\App\Modules\Communication\Models\Communication::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(\App\Modules\Ticket\Models\Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'customer_user_assignments', 'customer_id', 'user_id')
            ->withPivot('assigned_by', 'assigned_at', 'notes')
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerUserAssignment::class);
    }

    public function remoteLicenses(): HasMany
    {
        return $this->hasMany(CustomerRemoteLicense::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Sync customer type from leads: 'customer' if any lead is won, else 'prospect'.
     * Call this whenever a lead's stage may have changed to/from 'won'.
     */
    public function syncTypeFromLeads(): void
    {
        $hasWonLead = $this->leads()->where('stage', 'won')->exists();
        $newType = $hasWonLead ? self::TYPE_CUSTOMER : self::TYPE_PROSPECT;
        if ($this->type !== $newType) {
            $this->update(['type' => $newType]);
        }
    }

    public function isAssignedTo(int $userId): bool
    {
        return $this->assignedUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Sales/Call agents: can open this customer (and related leads, comms, timeline) if they created them,
     * are assigned as owner, assigned the customer to someone else, or own a lead on this customer.
     */
    public function salesAgentHasAccess(int $userId): bool
    {
        if ((int) $this->created_by === $userId) {
            return true;
        }
        if ($this->isAssignedTo($userId)) {
            return true;
        }
        if ($this->assignments()->where('assigned_by', $userId)->exists()) {
            return true;
        }
        if ($this->leads()->where('assigned_to', $userId)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Limit a customers query to rows visible to a Sales/Call agent (same rules as {@see salesAgentHasAccess()}).
     */
    public function scopeForSalesAgent(Builder $query, int $userId): void
    {
        $query->where(function (Builder $q) use ($userId) {
            $q->where('customers.created_by', $userId)
                ->orWhereHas('assignedUsers', fn (Builder $s) => $s->where('user_id', $userId))
                ->orWhereHas('assignments', fn (Builder $s) => $s->where('assigned_by', $userId))
                ->orWhereHas('leads', fn (Builder $l) => $l->where('assigned_to', $userId));
        });
    }

    public function assignTo(array $userIds, int $assignedBy, ?string $notes = null): void
    {
        $syncData = [];
        foreach ($userIds as $userId) {
            $syncData[$userId] = [
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'notes' => $notes,
            ];
        }
        $this->assignedUsers()->syncWithoutDetaching($syncData);
    }
}


