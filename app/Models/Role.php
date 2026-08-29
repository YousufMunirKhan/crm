<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    // Permission changes are the most security-relevant mutation in the app.
    use HasAuditLog;

    protected $fillable = [
        'name',
        'description',
        'nav_permissions',
    ];

    protected function casts(): array
    {
        return [
            'nav_permissions' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Admin / System Admin roles always have every sidebar section; cannot be limited. */
    public function hasFullMenuAccess(): bool
    {
        return in_array($this->name, ['Admin', 'System Admin'], true);
    }

    public static function nameHasFullMenuAccess(?string $name): bool
    {
        return $name !== null && in_array($name, ['Admin', 'System Admin'], true);
    }
}


