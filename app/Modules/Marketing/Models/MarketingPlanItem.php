<?php

namespace App\Modules\Marketing\Models;

use App\Models\EmailTemplate;
use App\Models\MessageTemplate;
use App\Models\SentCommunication;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One proposed message to one person.
 *
 * `blocked` is set by the guardrails, not by the planner - the AI is allowed to
 * suggest anyone, and the rails decide who is actually reachable. Keeping the
 * blocked rows rather than dropping them means the review screen can say
 * "12 skipped: no consent" instead of quietly showing a shorter list.
 */
class MarketingPlanItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'marketing_plan_id', 'customer_id', 'lead_id', 'channel', 'purpose',
        'email_template_id', 'message_template_id', 'reason', 'priority',
        'status', 'blocked_reason', 'subject_override', 'body_override',
        'scheduled_for', 'sent_communication_id',
    ];

    protected $casts = [
        'priority' => 'integer',
        'scheduled_for' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketingPlan::class, 'marketing_plan_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function sentCommunication(): BelongsTo
    {
        return $this->belongsTo(SentCommunication::class, 'sent_communication_id');
    }

    /** Only these actually go out. */
    public function scopeSendable(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }

    public function isEdited(): bool
    {
        return $this->subject_override !== null || $this->body_override !== null;
    }
}
