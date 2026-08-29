<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single marketing send, on one channel, to one audience.
 */
class Campaign extends Model
{
    use HasAuditLog, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'name', 'channel', 'status', 'template_id', 'template_type',
        'audience_filters', 'scheduled_at', 'started_at', 'completed_at',
        'recipient_count', 'sent_count', 'failed_count', 'skipped_count',
        'created_by',
    ];

    protected $casts = [
        'audience_filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function sends(): HasMany
    {
        return $this->hasMany(SentCommunication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Campaigns whose scheduled time has arrived and are ready to run. */
    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function markSending(): void
    {
        $this->update(['status' => self::STATUS_SENDING, 'started_at' => now()]);
    }

    public function markSent(): void
    {
        $this->update(['status' => self::STATUS_SENT, 'completed_at' => now()]);
    }

    /** Opens as a share of successful sends. */
    public function openRate(): float
    {
        if ($this->sent_count < 1) {
            return 0.0;
        }

        $opened = $this->sends()->whereNotNull('opened_at')->count();

        return round($opened / $this->sent_count * 100, 2);
    }
}
