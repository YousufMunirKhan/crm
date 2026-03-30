<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'customer_id',
        'lead_id',
        'campaign_id',
        'channel',
        'direction',
        'message',
        'media_url',
        'media_type',
        'status',
        'provider_payload',
    ];

    protected $casts = [
        'provider_payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Lead::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Communication\Models\WhatsAppCampaign::class, 'campaign_id');
    }
}

