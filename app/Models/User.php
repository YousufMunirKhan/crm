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
        ];
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

    public function assignedCustomers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\CRM\Models\Customer::class, 'customer_user_assignments', 'user_id', 'customer_id')
            ->withPivot('assigned_by', 'assigned_at', 'notes')
            ->withTimestamps();
    }
}
