<?php

namespace App\Models;

use App\Support\NavSections;
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
     * Sidebar section visibility.
     * User nav_permissions (if set) overrides role nav_permissions (if set); otherwise full menu.
     * Whitelist: only keys with true are shown. Dashboard always allowed. Admin/System Admin: all sections.
     */
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

    public function allowsNavSection(string $key): bool
    {
        if ($key === 'dashboard') {
            return true;
        }

        if ($this->isRole('Admin') || $this->isRole('System Admin')) {
            return true;
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
