<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'date',
        'amount',
        'currency',
        'reason',
        'description',
        'category',
        'status',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }
}

