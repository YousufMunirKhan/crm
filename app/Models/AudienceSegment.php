<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved, reusable audience definition.
 */
class AudienceSegment extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'filters', 'is_shared',
        'last_count', 'last_counted_at', 'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_shared' => 'boolean',
        'last_counted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** Segments the given user may use: their own, plus shared ones. */
    public function scopeVisibleTo($query, ?int $userId)
    {
        return $query->where(fn ($q) => $q->where('is_shared', true)->orWhere('created_by', $userId));
    }

    public function recordCount(int $count): void
    {
        $this->update(['last_count' => $count, 'last_counted_at' => now()]);
    }
}
