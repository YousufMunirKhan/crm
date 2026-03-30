<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'name',
        'message',
        'media_url',
        'media_type',
        'send_type',
        'selected_customer_ids',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'total_customers',
        'sent_count',
        'failed_count',
        'created_by',
        'error_message',
    ];

    protected $casts = [
        'selected_customer_ids' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'campaign_id');
    }
}

