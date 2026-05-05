<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ColdCallingContact extends Model
{
    protected $fillable = [
        'place_id',
        'name',
        'phone',
        'international_phone',
        'email',
        'email_source',
        'website',
        'formatted_address',
        'postcode_extracted',
        'latitude',
        'longitude',
        'types',
        'business_status',
        'google_maps_uri',
        'rating',
        'user_rating_count',
        'price_level',
        'editorial_summary',
        'opening_hours_summary',
        'extra_payload',
        'notes',
        'source',
        'first_seen_at',
        'last_seen_at',
        'prospect_marked_at',
        'prospect_stage',
        'assigned_to',
        'crm_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'opening_hours_summary' => 'array',
            'extra_payload' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'prospect_marked_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating' => 'decimal:2',
        ];
    }

    public function postcodeLinks(): HasMany
    {
        return $this->hasMany(ColdCallingContactPostcode::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function crmCustomer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class, 'crm_customer_id');
    }

    public function scopeProspectFilter($query, ?string $mode)
    {
        if ($mode === '1' || $mode === 'yes') {
            return $query->whereNotNull('prospect_marked_at');
        }
        if ($mode === '0' || $mode === 'no') {
            return $query->whereNull('prospect_marked_at');
        }

        return $query;
    }

    public function scopeSearch($query, ?string $q)
    {
        if ($q === null || trim($q) === '') {
            return $query;
        }
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($q)).'%';

        return $query->where(function ($qq) use ($term) {
            $qq->where('name', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('formatted_address', 'like', $term)
                ->orWhere('website', 'like', $term);
        });
    }

    public function scopeForPostcode($query, ?string $postcode)
    {
        if ($postcode === null || trim($postcode) === '') {
            return $query;
        }
        $n = self::normalizeUkPostcode($postcode);

        return $query->where(function ($qq) use ($n) {
            $qq->where('postcode_extracted', $n)
                ->orWhereHas('postcodeLinks', fn ($q) => $q->where('postcode_normalized', $n));
        });
    }

    /** `1` / `yes` = no email; `0` / `no` = has email; else no filter. */
    public function scopeFilterMissingEmail($query, ?string $mode)
    {
        if ($mode === '1' || $mode === 'yes') {
            return $query->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            });
        }
        if ($mode === '0' || $mode === 'no') {
            return $query->whereNotNull('email')->where('email', '!=', '');
        }

        return $query;
    }

    /** `1` / `yes` = has website URL; `0` / `no` = no website. */
    public function scopeFilterHasWebsite($query, ?string $mode)
    {
        if ($mode === '1' || $mode === 'yes') {
            return $query->whereNotNull('website')->where('website', '!=', '');
        }
        if ($mode === '0' || $mode === 'no') {
            return $query->where(function ($q) {
                $q->whereNull('website')->orWhere('website', '');
            });
        }

        return $query;
    }

    /** Google review count at most N (inclusive); null/unknown counts as passing (often smaller listings). */
    public function scopeFilterMaxReviews($query, $max)
    {
        if ($max === null || $max === '') {
            return $query;
        }
        $n = (int) $max;
        if ($n < 0) {
            return $query;
        }

        return $query->where(function ($q) use ($n) {
            $q->whereNull('user_rating_count')->orWhere('user_rating_count', '<=', $n);
        });
    }

    /**
     * Comma-separated fragments: exclude rows whose name contains any term (case-insensitive).
     * Useful to hide obvious chains (e.g. Tesco, McDonalds) — tune to your market.
     */
    public function scopeExcludeNameContains($query, ?string $csv): \Illuminate\Database\Eloquent\Builder
    {
        if ($csv === null || trim($csv) === '') {
            return $query;
        }
        $list = array_filter(array_map('trim', explode(',', $csv)));
        if ($list === []) {
            return $query;
        }
        $list = array_slice($list, 0, 20);
        foreach ($list as $term) {
            if ($term === '' || strlen($term) > 80) {
                continue;
            }
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $term);
            $like = '%'.strtolower($escaped).'%';
            $query->whereRaw('LOWER(name) NOT LIKE ?', [$like]);
        }

        return $query;
    }

    public static function normalizeUkPostcode(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');
    }
}
