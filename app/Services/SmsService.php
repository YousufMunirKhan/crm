<?php

namespace App\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private ?string $senderName = null;
    private ?string $defaultMessage = null;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * Load SMS settings from database
     */
    private function loadSettings(): void
    {
        $settings = Setting::whereIn('key', [
            'sms_api_key',
            'sms_secret_key',
            'sms_sender_name',
            'sms_default_message'
        ])->pluck('value', 'key');

        // Priority: Database > Config > Environment
        $this->apiKey = $settings['sms_api_key'] 
            ?? config('services.voodoosms.uid')
            ?? env('VOODOOSMS_UID')
            ?? env('SMS_API_KEY');
            
        $this->secretKey = $settings['sms_secret_key']
            ?? config('services.voodoosms.pass')
            ?? env('VOODOOSMS_PASS')
            ?? env('SMS_SECRET_KEY');
            
        $this->senderName = $settings['sms_sender_name']
            ?? config('services.voodoosms.sender_name', 'EPOS')
            ?? env('SMS_SENDER_NAME', 'EPOS');
            
        $this->defaultMessage = $settings['sms_default_message']
            ?? config('services.voodoosms.default_message')
            ?? env('SMS_DEFAULT_MESSAGE');
    }

    /**
     * Send SMS via VoodooSMS
     *
     * @param string $phoneNumber Phone number (077... or 447...)
     * @param string|null $message Message to send (uses default if not provided)
     * @return array ['success' => bool, 'response' => string, 'message' => string]
     */
    public function send(string $phoneNumber, ?string $message = null): array
    {
        try {
            // Validate phone number
            if (empty($phoneNumber)) {
                Log::warning('SMS skipped — no phone number provided');
                return [
                    'success' => false,
                    'response' => null,
                    'message' => 'Phone number is required'
                ];
            }

            // Check if settings are configured (both API key and secret key are required)
            if (!$this->apiKey || !$this->secretKey) {
                Log::warning('SMS skipped — missing SMS credentials', [
                    'has_api_key' => !empty($this->apiKey),
                    'has_secret_key' => !empty($this->secretKey)
                ]);
                return [
                    'success' => false,
                    'response' => null,
                    'message' => 'SMS API key and secret key are required. Please configure them in Settings.'
                ];
            }

            // Use default message if none provided
            if (empty($message)) {
                $message = $this->defaultMessage;
            }

            if (empty($message)) {
                Log::warning('SMS skipped — empty message');
                return [
                    'success' => false,
                    'response' => null,
                    'message' => 'Message is required'
                ];
            }

            // Send via VoodooSMS
            return $this->sendVoodooSms($phoneNumber, $message);
        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber
            ]);
            return [
                'success' => false,
                'response' => null,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via VoodooSMS HTTP API
     *
     * @param string $phone Phone number
     * @param string $message Message content
     * @return array
     */
    private function sendVoodooSms(string $phone, string $message): array
    {
        try {
            // Format phone number - remove spaces, +, and ensure it's numeric
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // If phone starts with 0, convert to international format (44)
            if (strpos($phone, '0') === 0) {
                $phone = '44' . substr($phone, 1);
            }
            
            // Ensure phone starts with country code (44 for UK)
            if (!str_starts_with($phone, '44')) {
                $phone = '44' . $phone;
            }

            // Validate required credentials
            if (empty($this->apiKey) || empty($this->secretKey)) {
                Log::error('VoodooSMS: Missing credentials', [
                    'has_api_key' => !empty($this->apiKey),
                    'has_secret_key' => !empty($this->secretKey)
                ]);
                return [
                    'success' => false,
                    'response' => json_encode(['result' => 400, 'resultText' => 'BAD REQUEST - Missing credentials']),
                    'message' => 'SMS send failed: API key and secret key are required'
                ];
            }

            $url = 'https://www.voodoosms.com/vapi/server/sendSMS';
            
            // Trim and validate sender name (max 11 characters)
            $senderName = trim($this->senderName ?? 'EPOS');
            if (strlen($senderName) > 11) {
                $senderName = substr($senderName, 0, 11);
                Log::warning('VoodooSMS: Sender name truncated', ['original' => $this->senderName, 'truncated' => $senderName]);
            }
            if (empty($senderName)) {
                $senderName = 'EPOS';
            }
            
            // Prepare parameters (same format as the PHP example)
            // Trim all values to avoid whitespace issues
            $params = [
                'uid' => trim($this->apiKey),              // VoodooSMS username/UID (trimmed)
                'pass' => trim($this->secretKey),          // VoodooSMS password (trimmed)
                'dest' => $phone,                          // Destination phone number (447...)
                'orig' => $senderName,                     // Origin/Sender name (max 11 chars)
                'msg' => $message,                         // Message content
                'format' => 'json',                        // Response format: json or xml
            ];
            
            // Validate all required parameters
            if (empty($params['uid']) || empty($params['pass']) || empty($params['dest']) || empty($params['msg'])) {
                Log::error('VoodooSMS: Missing required parameters', [
                    'has_uid' => !empty($params['uid']),
                    'has_pass' => !empty($params['pass']),
                    'has_dest' => !empty($params['dest']),
                    'has_msg' => !empty($params['msg']),
                    'uid_length' => strlen($params['uid'] ?? ''),
                    'pass_length' => strlen($params['pass'] ?? ''),
                ]);
                return [
                    'success' => false,
                    'response' => json_encode(['result' => 400, 'resultText' => 'BAD REQUEST - Missing required parameters']),
                    'message' => 'SMS send failed: Missing required parameters. Please check your settings.'
                ];
            }

            // Build full URL for debugging (without exposing password)
            $debugUrl = $url . '?uid=' . urlencode($this->apiKey) . '&pass=***&dest=' . urlencode($phone) . 
                       '&orig=' . urlencode($params['orig']) . '&msg=' . urlencode(substr($message, 0, 50)) . '...&format=json';
            
            Log::info('VoodooSMS API request', [
                'url' => $debugUrl,
                'uid' => $this->apiKey,
                'uid_length' => strlen($this->apiKey ?? ''),
                'pass_length' => strlen($this->secretKey ?? ''),
                'dest' => $phone,
                'dest_length' => strlen($phone),
                'orig' => $params['orig'],
                'orig_length' => strlen($params['orig']),
                'message_length' => strlen($message),
                'message_preview' => substr($message, 0, 100),
                'format' => $params['format'],
                'all_params' => array_merge($params, ['pass' => '***'])
            ]);

            // Use GET request (as shown in the PHP example)
            // Laravel Http will automatically URL encode parameters
            $response = Http::timeout(30)
                ->get($url, $params);

            $statusCode = $response->status();
            $responseBody = $response->body();
            
            // Try to decode JSON response
            $responseData = $response->json();
            
            Log::info('VoodooSMS API response', [
                'phone' => $phone,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'response_data' => $responseData,
                'headers' => $response->headers()
            ]);
            
            // Check for success
            // VoodooSMS returns: {"result": 200, "resultText": "OK"} on success
            // Or {"result": 400, "resultText": "BAD REQUEST"} on error
            $success = false;
            $errorMessage = 'SMS send failed';
            
            if ($responseData) {
                $result = $responseData['result'] ?? null;
                $resultText = $responseData['resultText'] ?? '';
                
                if ($result == 200) {
                    $success = true;
                    $errorMessage = 'SMS sent successfully';
                } else {
                    // Provide more detailed error message
                    $errorMessage = 'SMS send failed: ' . $resultText . ' (Result: ' . $result . ')';
                    
                    // Common error reasons
                    if ($result == 400) {
                        $errorMessage .= '. Possible causes: Invalid credentials, phone number format, or message encoding issue.';
                    } elseif ($result == 401) {
                        $errorMessage .= '. Authentication failed - check your UID and password.';
                    } elseif ($result == 402) {
                        $errorMessage .= '. Insufficient credits in your account.';
                    }
                }
            } else {
                // Try to parse as XML if JSON fails
                if (str_contains($responseBody, '<result>')) {
                    preg_match('/<result>(\d+)<\/result>/', $responseBody, $matches);
                    if (!empty($matches[1]) && $matches[1] == 200) {
                        $success = true;
                        $errorMessage = 'SMS sent successfully';
                    } else {
                        $errorMessage = 'SMS send failed: ' . $responseBody;
                    }
                } else {
                    $errorMessage = 'SMS send failed: Invalid response - ' . $responseBody;
                }
            }

            return [
                'success' => $success,
                'response' => $responseBody,
                'message' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('VoodooSMS API exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'response' => json_encode(['result' => 400, 'resultText' => 'BAD REQUEST']),
                'message' => 'VoodooSMS API error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test SMS configuration
     *
     * @param string $phoneNumber Test phone number
     * @param string|null $message Test message
     * @return array
     */
    public function test(string $phoneNumber, ?string $message = null): array
    {
        $testMessage = $message ?? 'This is a test SMS from your CRM system.';
        return $this->send($phoneNumber, $testMessage);
    }
}

