<?php

namespace App\Models;

use App\Support\NavSections;
use App\Models\UserPermissionGrant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasAuditLog, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'employee_type',
        'phone',
        'address',
        'hire_date',
        'date_of_birth',
        'bank_account_name',
        'bank_name',
        'bank_sort_code',
        'bank_account_number',
        'contract_sent_at',
        'contract_pdf_path',
        'is_active',
        'commission_eligible',
        'nav_permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hire_date' => 'date',
            'date_of_birth' => 'date',
            'contract_sent_at' => 'datetime',
            'commission_eligible' => 'boolean',
            'nav_permissions' => 'array',
        ];
    }

    /**
     * Gates access to the Filament back-office panel.
     *
     * Deliberately narrow: the panel exposes catalogue, payroll and settings,
     * so only management roles get in. Sales and field staff keep using the
     * SPA, which is where their work lives.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (property_exists($this, 'attributes') && array_key_exists('is_active', $this->attributes) && ! $this->is_active) {
            return false;
        }

        return $this->isRole('Admin')
            || $this->isRole('System Admin')
            || $this->isRole('Manager');
    }

    /**
     * Can this person see and use a section of the app?
     *
     * Read in order, first answer wins:
     *
     *  1. Dashboard is always allowed - somewhere has to be home.
     *  2. Admin and System Admin see everything, and cannot be limited.
     *  3. A live per-user exception. This is the layer that was missing: an
     *     admin can hand one person one section without inventing a role for
     *     them, and it can carry an end date so it undoes itself.
     *  4. The person's own nav_permissions, if anyone still has them. That
     *     replaces the role rather than adding to it, which is exactly why it
     *     went unused; kept only so existing rows keep behaving as they did.
     *  5. Their role's whitelist.
     *  6. Otherwise allowed.
     */
    public function allowsNavSection(string $key): bool
    {
        if ($key === 'dashboard') {
            return true;
        }

        if ($this->isRole('Admin') || $this->isRole('System Admin')) {
            return true;
        }

        $override = $this->navSectionOverride($key);
        if ($override !== null) {
            return $override;
        }

        $userP = $this->nav_permissions;
        if (is_array($userP) && $userP !== []) {
            return $this->navWhitelistAllows($userP, $key);
        }

        $this->loadMissing('role');
        $roleP = $this->role?->nav_permissions;
        if (is_array($roleP) && $roleP !== []) {
            return $this->navWhitelistAllows($roleP, $key);
        }

        return true;
    }

    /**
     * A live exception for one section, or null when the role decides.
     *
     * A revoke beats a grant on the same section: if somebody has been
     * deliberately shut out of something, another row must not quietly let them
     * back in.
     */
    public function navSectionOverride(string $key): ?bool
    {
        $rows = $this->relationLoaded('permissionGrants')
            ? $this->permissionGrants->where('section', $key)->filter->isActive()
            : $this->permissionGrants()->active()->where('section', $key)->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return ! $rows->contains('effect', UserPermissionGrant::EFFECT_REVOKE);
    }

    public function permissionGrants(): HasMany
    {
        return $this->hasMany(UserPermissionGrant::class);
    }

    /**
     * Every section this person can reach, for the client to hold.
     *
     * The SPA used to reimplement this whole precedence chain in its auth store
     * and had already drifted from it - POS Support was special-cased on one
     * side only. Sending the answer removes the second implementation.
     *
     * @return array<string, bool>
     */
    public function navSectionMap(): array
    {
        $out = [];

        foreach (NavSections::keys() as $key) {
            $out[$key] = $key === 'pos_support'
                ? $this->canAccessPosSupport()
                : $this->allowsNavSection($key);
        }

        return $out;
    }

    /**
     * Whitelist entry is allowed if the key is true, or a legacy key (leads_pipeline,
     * marketing, hr) covers the finer-grained key it was split into.
     */
    private function navWhitelistAllows(array $permissions, string $key): bool
    {
        if (! empty($permissions[$key])) {
            return true;
        }

        return NavSections::legacyGrants($permissions, $key);
    }

    /**
     * POS Support queue and tickets: never "default open" when nav_permissions are empty.
     * Grant only to Admin / Manager / System Admin, or explicit pos_support on user/role whitelist.
     */
    public function canAccessPosSupport(): bool
    {
        if ($this->isRole('Admin') || $this->isRole('Manager') || $this->isRole('System Admin')) {
            return true;
        }

        $override = $this->navSectionOverride('pos_support');
        if ($override !== null) {
            return $override;
        }

        $userP = $this->nav_permissions;
        if (is_array($userP) && $userP !== []) {
            return ! empty($userP['pos_support']);
        }

        $this->loadMissing('role');
        $roleP = $this->role?->nav_permissions;
        if (is_array($roleP) && $roleP !== []) {
            return ! empty($roleP['pos_support']);
        }

        return false;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function leads(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\Lead::class, 'assigned_to');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(\App\Modules\Ticket\Models\Ticket::class, 'assigned_to');
    }

    /**
     * Tickets where this user is in the multi-assignee list (CRM tickets).
     */
    public function coAssignedTickets(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Ticket\Models\Ticket::class, 'ticket_assignees', 'user_id', 'ticket_id')
            ->withTimestamps();
    }

    public function assignedCustomers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\CRM\Models\Customer::class, 'customer_user_assignments', 'user_id', 'customer_id')
            ->withPivot('assigned_by', 'assigned_at', 'notes')
            ->withTimestamps();
    }

    public function commissionSales(): HasMany
    {
        return $this->hasMany(CommissionSale::class, 'credited_user_id');
    }
}
