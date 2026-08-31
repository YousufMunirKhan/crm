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

    /** A draft can still be edited and sent; anything else is history. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true);
    }

    public function scopeForWeek(Builder $query, string $weekStarting): void
    {
        $query->whereDate('week_starting', $weekStarting);
    }
}
