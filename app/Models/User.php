<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasAuditLog;

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
            'nav_permissions' => 'array',
        ];
    }

    /**
     * Sidebar section visibility.
     * User nav_permissions (if set) overrides role nav_permissions (if set); otherwise full menu.
     * Whitelist: only keys with true are shown. Dashboard always allowed. Admin/System Admin: all sections.
     */
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
            return ! empty($userP[$key]);
        }

        $this->loadMissing('role');
        $roleP = $this->role?->nav_permissions;
        if (is_array($roleP) && $roleP !== []) {
            return ! empty($roleP[$key]);
        }

        return true;
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
}
