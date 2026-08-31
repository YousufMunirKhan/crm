<?php

namespace App\Modules\Marketing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that happened to a plan or one of its rows.
 *
 * A status column says where something ended up; it cannot say who decided, or
 * when, or what it was before. After a send goes out the question is always
 * "who cancelled that one?" - this is where that is answered.
 */
class MarketingPlanEvent extends Model
{
    public const GENERATED = 'generated';
    public const GENERATION_FAILED = 'generation_failed';
    public const SUPERSEDED = 'superseded';
    public const APPROVED = 'approved';
    public const SKIPPED = 'skipped';
    public const REOPENED = 'reopened';
    public const EDITED = 'edited';
    public const TEMPLATE_EDITED = 'template_edited';
    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const BLOCKED = 'blocked';

    protected $fillable = [
        'marketing_plan_id', 'marketing_plan_item_id', 'user_id',
        'action', 'summary', 'context',
    ];

    protected $casts = ['context' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketingPlan::class, 'marketing_plan_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketingPlanItem::class, 'marketing_plan_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Records an event. Deliberately tolerant: an audit trail that throws would
     * roll back the very action it exists to describe.
     */
    public static function record(
        int $planId,
        string $action,
        string $summary,
        ?int $itemId = null,
        ?int $userId = null,
        ?array $context = null,
    ): void {
        try {
            static::create([
                'marketing_plan_id' => $planId,
                'marketing_plan_item_id' => $itemId,
                'user_id' => $userId ?? auth()->id(),
                'action' => $action,
                'summary' => $summary,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not record marketing plan event', [
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
