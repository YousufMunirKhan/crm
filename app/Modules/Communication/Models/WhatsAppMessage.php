<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'direction',
        'to_e164',
        'from_e164',
        'type',
        'body_text',
        'template_name',
        'template_params_json',
        'meta_wamid',
        'status',
        'meta_payload_json',
    ];

    protected $casts = [
        'template_params_json' => 'array',
        'meta_payload_json' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    /**
     * Check if message is sent
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    /**
     * Check if message is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

