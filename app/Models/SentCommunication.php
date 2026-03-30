<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentCommunication extends Model
{
    protected $fillable = [
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
        'sent_at',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
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
}
