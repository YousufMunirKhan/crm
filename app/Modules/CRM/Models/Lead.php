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

    /**
     * When a deal is marked won, align lead_items with reporting (targets, dashboards, "what customer has"):
     * - Every pending line item becomes won with closed_at set.
     * - If there are no line items but lead.product_id is set (legacy / edge case), create one won line item.
     * - Refreshes pipeline_value from the sum of won line totals.
     *
     * @param  bool  $logActivities  When true, writes item_closed activities (e.g. follow-up completion).
     */
    public function materializeWonLineItemsForReporting(bool $logActivities = false): void
    {
        $pending = $this->items()->where('status', LeadItem::STATUS_PENDING)->get();
        foreach ($pending as $item) {
            $item->status = LeadItem::STATUS_WON;
            $item->closed_at = now();
            if (! $item->quantity || (int) $item->quantity < 1) {
                $item->quantity = 1;
            }
            if ($item->unit_price === null) {
                $item->unit_price = 0;
            }
            $item->save();

            if ($logActivities && auth()->check()) {
                $productName = $item->product->name ?? 'Unknown';
                LeadActivity::create([
                    'lead_id' => $this->id,
                    'user_id' => auth()->id(),
                    'type' => 'item_closed',
                    'description' => "Product '{$productName}' auto-marked as WON when deal was marked won.",
                    'meta' => [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'status' => LeadItem::STATUS_WON,
                        'auto_closed' => true,
                    ],
                ]);
            }
        }

        $this->load('items');
        if ($this->items->count() === 0 && $this->product_id) {
            $item = LeadItem::create([
                'lead_id' => $this->id,
                'product_id' => $this->product_id,
                'quantity' => 1,
                'unit_price' => (float) ($this->pipeline_value ?: 0),
                'status' => LeadItem::STATUS_WON,
                'closed_at' => now(),
            ]);

            if ($logActivities && auth()->check()) {
                $productName = $item->product->name ?? 'Unknown';
                LeadActivity::create([
                    'lead_id' => $this->id,
                    'user_id' => auth()->id(),
                    'type' => 'item_closed',
                    'description' => "Product '{$productName}' recorded as WON from primary product when deal was marked won.",
                    'meta' => [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'status' => LeadItem::STATUS_WON,
                        'auto_closed' => true,
                        'from_primary_product' => true,
                    ],
                ]);
            }
        }

        $wonSum = (float) $this->wonItems()->sum('total_price');
        static::query()->whereKey($this->id)->update(['pipeline_value' => $wonSum]);
        $this->refresh();
    }
}


