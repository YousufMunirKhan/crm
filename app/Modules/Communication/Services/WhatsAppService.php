<?php

namespace App\Modules\Communication\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class WhatsAppService
{
    private string $apiUrl;
    private ?string $phoneNumberId;
    private ?string $accessToken;
    private string $verifyToken;
    private ?string $appSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id') ?: env('WHATSAPP_PHONE_NUMBER_ID');
        $this->accessToken = config('services.whatsapp.access_token') ?: env('WHATSAPP_ACCESS_TOKEN');
        $this->verifyToken = config('services.whatsapp.verify_token') ?: env('WHATSAPP_VERIFY_TOKEN', 'your_verify_token');
        $this->appSecret = config('services.whatsapp.app_secret') ?: env('WHATSAPP_APP_SECRET');
    }

    /**
     * Send WhatsApp message via Meta Cloud API
     */
    public function sendMessage(string $to, string $message, ?string $phoneNumberId = null, ?string $mediaUrl = null, ?string $mediaType = 'image'): array
    {
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;
        
        if (!$phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp credentials check failed', [
                'phoneNumberId' => $phoneNumberId ? 'set' : 'missing',
                'accessToken' => $this->accessToken ? 'set' : 'missing',
                'env_phone' => env('WHATSAPP_PHONE_NUMBER_ID') ? 'set' : 'missing',
                'env_token' => env('WHATSAPP_ACCESS_TOKEN') ? 'set' : 'missing',
            ]);
            throw new \Exception('WhatsApp API credentials not configured. Phone Number ID: ' . ($phoneNumberId ? 'OK' : 'MISSING') . ', Access Token: ' . ($this->accessToken ? 'OK' : 'MISSING'));
        }

        // Format phone number (remove +, spaces, etc.)
        $to = $this->formatPhoneNumber($to);

        $url = "{$this->apiUrl}/{$phoneNumberId}/messages";

        // If media URL is provided, send as media message
        if ($mediaUrl) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => $mediaType, // image, document, video
                $mediaType => [
                    'link' => $mediaUrl,
                ],
            ];

            // Add caption if message is provided
            if ($message && $mediaType === 'image') {
                $payload['image']['caption'] = $message;
            } elseif ($message && $mediaType === 'document') {
                $payload['document']['caption'] = $message;
            } elseif ($message && $mediaType === 'video') {
                $payload['video']['caption'] = $message;
            }
        } else {
            // Text message
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'],
                    'response' => $responseData,
                ];
            }

            throw new \Exception('WhatsApp API error: ' . json_encode($responseData));
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature from Meta
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        if (!$this->appSecret) {
            // If app secret not configured, skip verification (not recommended for production)
            Log::warning('WhatsApp webhook verification skipped - app secret not configured');
            return true;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->appSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify webhook challenge (for initial setup)
     */
    public function verifyWebhookChallenge(string $mode, string $token, string $challenge): ?string
    {
        if ($mode === 'subscribe' && $token === $this->verifyToken) {
            return $challenge;
        }

        return null;
    }

    /**
     * Format phone number for WhatsApp API
     * Removes +, spaces, and ensures it's in international format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove + if present
        $phone = ltrim($phone, '+');
        
        // If phone doesn't start with country code, assume UK (44)
        if (!preg_match('/^[1-9]\d{1,14}$/', $phone)) {
            // If it starts with 0, replace with country code
            if (strpos($phone, '0') === 0) {
                $phone = '44' . substr($phone, 1);
            }
        }

        return $phone;
    }

    /**
     * Get information for adding phone number to Meta allowed list
     * Meta doesn't provide API for this, but we can provide formatted number and direct link
     */
    public function getAddPhoneNumberInfo(string $phone): array
    {
        $formatted = $this->formatPhoneNumber($phone);
        $appId = env('WHATSAPP_APP_ID', '968377435537226');
        
        // Direct link to Meta App WhatsApp setup page
        $addUrl = "https://developers.facebook.com/apps/{$appId}/whatsapp-business/cloud-api/get-started";
        
        return [
            'formatted_number' => $formatted,
            'add_url' => $addUrl,
            'instructions' => "Add this number ({$formatted}) to Meta Business Account allowed list",
        ];
    }

    /**
     * Parse incoming webhook data from Meta
     */
    public function parseWebhookData(array $data): ?array
    {
        // Meta webhook structure:
        // {
        //   "object": "whatsapp_business_account",
        //   "entry": [{
        //     "changes": [{
        //       "value": {
        //         "messages": [{
        //           "from": "1234567890",
        //           "text": { "body": "message text" }
        //         }]
        //       }
        //     }]
        //   }]
        // }

        if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            return null;
        }

        $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
        
        return [
            'from' => $message['from'] ?? null,
            'message' => $message['text']['body'] ?? $message['text']['body'] ?? '',
            'message_id' => $message['id'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
        ];
    }
}

