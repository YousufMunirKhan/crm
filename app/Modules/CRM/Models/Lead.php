<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'customer_id',
        'product_id',
        'converted_from_activity_id',
        'stage',
        'source',
        'assigned_to',
        'pipeline_value',
        'lost_reason',
        'next_follow_up_at',
        'expected_closing_date',
    ];

    protected $casts = [
        'pipeline_value' => 'decimal:2',
        'next_follow_up_at' => 'datetime',
        'expected_closing_date' => 'date',
    ];

    protected $appends = ['total_value'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if ($lead->created_by === null && auth()->check()) {
                $lead->created_by = auth()->id();
            }
        });
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(LeadAssignmentLog::class)->orderBy('assigned_at', 'desc');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(\App\Modules\Communication\Models\Communication::class);
    }

    public function convertedFromActivity(): BelongsTo
    {
        return $this->belongsTo(LeadActivity::class, 'converted_from_activity_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\LeadItem::class);
    }

    /**
     * Get products through items - for displaying products on a lead
     */
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            LeadItem::class,
            'lead_id',
            'id',
            'id',
            'product_id'
        );
    }

    /**
     * Get pending items (not yet closed)
     */
    public function pendingItems(): HasMany
    {
        return $this->items()->where('status', LeadItem::STATUS_PENDING);
    }

    /**
     * Get won items
     */
    public function wonItems(): HasMany
    {
        return $this->items()->where('status', LeadItem::STATUS_WON);
    }

    /**
     * Get lost items
     */
    public function lostItems(): HasMany
    {
        return $this->items()->where('status', LeadItem::STATUS_LOST);
    }

    /**
     * Get total value - for won leads, use items total; otherwise use pipeline_value
     */
    public function getTotalValueAttribute()
    {
        if ($this->stage === 'won') {
            // If items are loaded, use them; otherwise query
            if ($this->relationLoaded('items')) {
                $itemsTotal = $this->items->where('status', LeadItem::STATUS_WON)->sum('total_price');
            } else {
                $itemsTotal = $this->wonItems()->sum('total_price');
            }
            return $itemsTotal > 0 ? $itemsTotal : $this->pipeline_value;
        }
        return $this->pipeline_value;
    }

    /**
     * Check if all items are closed (won or lost)
     */
    public function allItemsClosed(): bool
    {
        return $this->items()->where('status', LeadItem::STATUS_PENDING)->count() === 0;
    }

    /**
     * Automatically update lead stage based on item statuses
     */
    public function updateStageFromItems(): void
    {
        $pendingCount = $this->items()->where('status', LeadItem::STATUS_PENDING)->count();
        $wonCount = $this->items()->where('status', LeadItem::STATUS_WON)->count();
        $lostCount = $this->items()->where('status', LeadItem::STATUS_LOST)->count();
        $totalCount = $this->items()->count();

        if ($totalCount === 0) {
            return; // No items, don't change stage
        }

        if ($pendingCount === 0) {
            // All items are closed
            if ($wonCount > 0 && $lostCount === 0) {
                $this->stage = 'won';
            } elseif ($lostCount > 0 && $wonCount === 0) {
                $this->stage = 'lost';
            } else {
                // Mixed - some won, some lost - consider it won with partial success
                $this->stage = 'won';
            }
            $this->save();
        }
    }
}


