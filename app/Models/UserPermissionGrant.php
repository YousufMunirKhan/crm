<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exception to somebody's role: one section, granted or taken away.
 *
 * @see \App\Support\NavSections for the valid section keys.
 */
class UserPermissionGrant extends Model
{
    // Permission changes are the most security-relevant mutation in the app.
    use HasAuditLog;

    public const EFFECT_GRANT = 'grant';
    public const EFFECT_REVOKE = 'revoke';

    protected $fillable = [
        'user_id',
        'section',
        'effect',
        'expires_at',
        'granted_by',
        'reason',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * In force right now: not switched off by hand, and not past its date.
     *
     * Expiry is enforced here rather than by a nightly job, so temporary access
     * really does end on time even if nothing has run since.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** How it reads on the employee page: "Invoices until 5 Sep - covering for Aziza". */
    public function summary(): string
    {
        $label = \App\Support\NavSections::labels()[$this->section] ?? $this->section;
        $verb = $this->effect === self::EFFECT_REVOKE ? 'Blocked from' : 'Extra access to';
        $until = $this->expires_at ? ' until '.$this->expires_at->format('j M Y') : ' (no end date)';

        return $verb.' '.$label.$until.($this->reason ? ' - '.$this->reason : '');
    }
}
