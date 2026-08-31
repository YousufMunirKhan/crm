<?php

namespace App\Modules\Marketing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One week's marketing plan: who to contact, on what channel, and why.
 *
 * A plan never sends on its own. It is generated as a draft, a person reviews
 * it row by row, and only approved rows go anywhere.
 */
class MarketingPlan extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    /** Replaced by a rebuild. Kept so the week's history survives. */
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'week_starting', 'status', 'model', 'generated_at', 'generated_by',
        'approved_by', 'approved_at', 'item_count', 'sent_count', 'failed_count',
        'rail_summary', 'notes', 'generation_error',
        'superseded_by_id', 'superseded_at',
    ];

    protected $casts = [
        'week_starting' => 'date',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'rail_summary' => 'array',
        'superseded_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(MarketingPlanEvent::class)->latest('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketingPlanItem::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * A week stays workable until it is replaced or cancelled.
     *
     * Sending part of a plan used to lock the rest of it, which made the sane
     * way to run this - send a small group, look at the results, then decide
     * about the others - impossible. Rows that have already gone are protected
     * by their own status; the ones still waiting are nobody's history yet.
     */
    public function isEditable(): bool
    {
        return ! in_array($this->status, [self::STATUS_SUPERSEDED, self::STATUS_CANCELLED], true);
    }

    /** Nothing left to decide. */
    public function isFinished(): bool
    {
        return $this->items()
            ->whereIn('status', [MarketingPlanItem::STATUS_PENDING, MarketingPlanItem::STATUS_APPROVED])
            ->doesntExist();
    }

    public function scopeForWeek(Builder $query, string $weekStarting): void
    {
        $query->whereDate('week_starting', $weekStarting);
    }
}
