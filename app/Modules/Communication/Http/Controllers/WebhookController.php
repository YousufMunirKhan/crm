<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Services\CommunicationService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private CommunicationService $communicationService
    ) {}

    public function whatsapp(Request $request)
    {
        $whatsappService = app(\App\Modules\Communication\Services\WhatsAppService::class);

        // Handle webhook verification (GET request from Meta)
        if ($request->isMethod('GET')) {
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            $challengeResponse = $whatsappService->verifyWebhookChallenge($mode, $token, $challenge);
            
            if ($challengeResponse) {
                return response($challengeResponse, 200);
            }

            return response('Forbidden', 403);
        }

        // Handle incoming messages (POST request)
        // Verify webhook signature
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();

        if ($signature) {
            // Remove 'sha256=' prefix if present
            $signature = str_replace('sha256=', '', $signature);
            
            if (!$whatsappService->verifyWebhookSignature($signature, $payload)) {
                \Illuminate\Support\Facades\Log::warning('WhatsApp webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        $data = $request->all();

        // Parse Meta webhook format
        $parsedData = $whatsappService->parseWebhookData($data);
        
        if (!$parsedData) {
            // If not a message, might be status update or other event
            \Illuminate\Support\Facades\Log::info('WhatsApp webhook received non-message event', ['data' => $data]);
            return response()->json(['ok' => true]);
        }

        // Convert to format expected by CommunicationService
        $serviceData = [
            'from' => $parsedData['from'],
            'phone' => $parsedData['from'],
            'text' => $parsedData['message'],
            'message' => $parsedData['message'],
            'body' => $parsedData['message'],
            'message_id' => $parsedData['message_id'],
            'timestamp' => $parsedData['timestamp'],
        ];

        $communication = $this->communicationService->handleInbound($serviceData, 'whatsapp');

        if ($communication) {
            // Broadcast real-time notification
            event(new \App\Events\NewMessageReceived($communication));
        }

        return response()->json(['ok' => true]);
    }

    public function email(Request $request)
    {
        $data = $request->all();
        $this->communicationService->handleInbound($data, 'email');
        return response()->json(['ok' => true]);
    }

    public function sms(Request $request)
    {
        $data = $request->all();
        $this->communicationService->handleInbound($data, 'sms');
        return response()->json(['ok' => true]);
    }
}


