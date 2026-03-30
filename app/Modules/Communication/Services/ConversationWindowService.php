<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\WhatsAppConversation;
use App\Modules\CRM\Models\Customer;
use Carbon\Carbon;

class ConversationWindowService
{
    /**
     * Get or create conversation for customer/phone
     */
    public function getOrCreateConversation(?Customer $customer, string $phoneE164): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::where('customer_phone_e164', $phoneE164)
            ->first();

        if (!$conversation) {
            $conversation = WhatsAppConversation::create([
                'customer_id' => $customer?->id,
                'customer_phone_e164' => $phoneE164,
            ]);
        } elseif ($customer && !$conversation->customer_id) {
            $conversation->update(['customer_id' => $customer->id]);
        }

        return $conversation;
    }

    /**
     * Check if customer is within 24-hour window
     */
    public function isWithinWindow(?Customer $customer, string $phoneE164): bool
    {
        $conversation = WhatsAppConversation::where('customer_phone_e164', $phoneE164)
            ->first();

        if (!$conversation) {
            return false;
        }

        return $conversation->isWithinWindow();
    }

    /**
     * Update window after inbound message
     */
    public function updateWindowAfterInbound(string $phoneE164, ?Customer $customer = null): WhatsAppConversation
    {
        $conversation = $this->getOrCreateConversation($customer, $phoneE164);
        
        $conversation->update([
            'last_inbound_at' => now(),
            'window_expires_at' => now()->addHours(24),
        ]);

        return $conversation;
    }

    /**
     * Update window after outbound message
     */
    public function updateWindowAfterOutbound(string $phoneE164, ?Customer $customer = null): WhatsAppConversation
    {
        $conversation = $this->getOrCreateConversation($customer, $phoneE164);
        
        $conversation->update([
            'last_outbound_at' => now(),
        ]);

        return $conversation;
    }

    /**
     * Format phone number to E.164 format
     * Defaults to +44 (UK) if no country code detected
     */
    public function formatToE164(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove leading +
        $phone = ltrim($phone, '+');
        
        // If starts with 0, assume UK and replace with 44
        if (strpos($phone, '0') === 0) {
            $phone = '44' . substr($phone, 1);
        }
        
        // If doesn't start with country code, assume UK (44)
        if (!preg_match('/^[1-9]\d{1,14}$/', $phone)) {
            // Already has country code, return as is
            if (strlen($phone) >= 10) {
                return $phone;
            }
            // Too short, assume UK
            $phone = '44' . $phone;
        }
        
        return $phone;
    }
}

