<?php

namespace App\Modules\Communication\Models;

use App\Modules\CRM\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'customer_id',
        'customer_phone_e164',
        'last_inbound_at',
        'last_outbound_at',
        'window_expires_at',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'window_expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    /**
     * Check if conversation is within 24-hour window
     */
    public function isWithinWindow(): bool
    {
        if (!$this->window_expires_at) {
            return false;
        }

        return $this->window_expires_at->isFuture();
    }

    /**
     * Update window expiration (24 hours from last inbound)
     */
    public function updateWindow(): void
    {
        if ($this->last_inbound_at) {
            $this->window_expires_at = $this->last_inbound_at->copy()->addHours(24);
            $this->save();
        }
    }
}

