<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppApiLog;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Services\WhatsAppServiceV2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct()
    {
        $this->whatsappService = app(\App\Modules\Communication\Services\WhatsAppServiceV2::class);
    }

    private WhatsAppServiceV2 $whatsappService;

    /**
     * Verify webhook (GET request from Meta)
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Get verify token from settings or config
        $settings = WhatsAppSetting::first();
        $verifyToken = $settings?->verify_token ?? config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            // Log webhook verification
            WhatsAppApiLog::create([
                'correlation_id' => uniqid('verify_', true),
                'direction' => 'INBOUND',
                'endpoint' => '/api/whatsapp/webhook',
                'method' => 'GET',
                'status_code' => 200,
                'request_json' => $request->all(),
                'response_json' => ['challenge' => $challenge],
            ]);

            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle webhook (POST request from Meta)
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        $correlationId = uniqid('webhook_', true);

        try {
            // Log incoming webhook
            WhatsAppApiLog::create([
                'correlation_id' => $correlationId,
                'direction' => 'INBOUND',
                'endpoint' => '/api/whatsapp/webhook',
                'method' => 'POST',
                'status_code' => 200,
                'request_json' => WhatsAppApiLog::redactSensitiveData($data),
                'response_json' => null,
            ]);

            // Verify webhook signature (if app_secret is configured)
            $settings = WhatsAppSetting::first();
            $appSecret = config('services.whatsapp.app_secret');
            
            if ($appSecret) {
                $signature = $request->header('X-Hub-Signature-256');
                if ($signature) {
                    $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
                    if (!hash_equals($expectedSignature, $signature)) {
                        Log::warning('WhatsApp webhook signature verification failed', [
                            'correlation_id' => $correlationId,
                        ]);
                        return response('Invalid signature', 403);
                    }
                }
            }

            // Process webhook data
            $entry = $data['entry'][0] ?? null;
            if (!$entry) {
                return response()->json(['status' => 'ok']);
            }

            $changes = $entry['changes'][0] ?? null;
            if (!$changes) {
                return response()->json(['status' => 'ok']);
            }

            $field = $changes['field'] ?? null;
            $value = $changes['value'] ?? [];

            if ($field === 'messages') {
                // Handle incoming message
                $this->whatsappService->handleInboundMessage($data);
                
                // Handle status updates
                if (isset($value['statuses'])) {
                    $this->whatsappService->handleStatusUpdate($data);
                }
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing failed', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            WhatsAppApiLog::create([
                'correlation_id' => $correlationId,
                'direction' => 'INBOUND',
                'endpoint' => '/api/whatsapp/webhook',
                'method' => 'POST',
                'status_code' => 500,
                'request_json' => WhatsAppApiLog::redactSensitiveData($data),
                'response_json' => null,
                'error' => $e->getMessage(),
            ]);

            // Still return 200 to Meta to prevent retries
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }
}

