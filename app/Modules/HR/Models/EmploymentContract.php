<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentContract extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'user_id',
        'template_type',
        'contract_data',
        'pdf_path',
        'sent_at',
        'signed_at',
    ];

    protected $casts = [
        'contract_data' => 'array',
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

