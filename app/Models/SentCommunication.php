<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentCommunication extends Model
{
    protected $fillable = [
        'campaign_id',
        'type',
        'template_type',
        'template_id',
        'customer_id',
        'lead_id',
        'recipient_email',
        'recipient_phone',
        'subject',
        'content',
        'status',
        'error_message',
        'opened_at',
        'open_count',
        'failure_category',
        'sent_at',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Lead::class);
    }

    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by');
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function clicks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CommunicationClick::class, 'sent_communication_id');
    }
}
