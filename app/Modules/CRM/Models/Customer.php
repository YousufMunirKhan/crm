<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditLog;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
    use HasAuditLog, HasApiTokens, SoftDeletes;

    public const TYPE_PROSPECT = 'prospect';
    public const TYPE_CUSTOMER = 'customer';

    protected $fillable = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'referrer', 'landing_page', 'gclid', 'fbclid',
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

    /**
     * Never serialise credentials. 'passwords' and 'anydesk_rustdesk' are
     * customer remote-access credentials and must not reach the API.
     */
    protected $hidden = ['portal_password', 'passwords', 'anydesk_rustdesk'];

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

    /**
     * Every text field on a customer that someone might realistically type into
     * a search box.
     *
     * Kept here rather than in the controller because leads, invoices and
     * tickets all need to search "the customer" too, and they had each grown a
     * different, narrower list - searching a ticket by company name silently
     * found nothing while the same search on the customers page worked.
     */
    public const SEARCHABLE = [
        'name',
        'business_name',
        'owner_name',
        'contact_person_2_name',
        'email',
        'email_secondary',
        'address',
        'postcode',
        'city',
        'vat_number',
        'epos_type',
        'source',
        'notes',
    ];

    /**
     * Phone-ish columns. Searched separately because they are matched on digits
     * only - see {@see scopeSearch()}.
     */
    public const SEARCHABLE_PHONES = [
        'phone',
        'whatsapp_number',
        'sms_number',
        'contact_person_2_phone',
    ];

    /**
     * Free-text search across a customer.
     *
     * Phone numbers get their own treatment: the same mobile is stored as
     * "07700 900123", "+44 7700 900123" and "447700900123" depending on whether
     * it was typed, imported or captured from WhatsApp, so a plain LIKE on what
     * the user typed misses most of them. When the term is mostly digits we
     * strip the punctuation out of the column too and compare digits to digits,
     * ignoring a leading 0 or 44 on either side.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($like, $digits) {
            foreach (self::SEARCHABLE as $column) {
                $q->orWhere('customers.'.$column, 'like', $like);
            }

            foreach (self::SEARCHABLE_PHONES as $column) {
                $q->orWhere('customers.'.$column, 'like', $like);
            }

            // Four or more digits, otherwise "07" would match half the database.
            if (strlen((string) $digits) >= 4) {
                $needle = self::nationalPhoneDigits($digits);

                foreach (self::SEARCHABLE_PHONES as $column) {
                    $stripped = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customers.$column, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";
                    $q->orWhereRaw("$stripped LIKE ?", ['%'.$needle])
                        ->orWhereRaw("$stripped LIKE ?", [$needle.'%']);
                }
            }
        });
    }

    /**
     * Drops a UK country code or trunk prefix so 447700900123, 07700900123 and
     * 7700900123 all reduce to the same needle.
     */
    protected static function nationalPhoneDigits(string $digits): string
    {
        if (str_starts_with($digits, '44')) {
            return substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            return ltrim($digits, '0');
        }

        return $digits;
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


