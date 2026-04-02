<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppConversation;
use App\Modules\Communication\Models\WhatsAppMessage;
use App\Modules\Communication\Models\WhatsAppTemplate;
use App\Modules\Communication\Exceptions\WhatsAppGraphApiException;
use App\Modules\Communication\Support\WhatsAppApiErrorResponse;
use App\Modules\Communication\Services\ConversationWindowService;
use App\Modules\Communication\Services\WhatsAppServiceV2;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Http\Request;

class WhatsAppMessageController extends Controller
{
    public function __construct()
    {
        $this->whatsappService = app(\App\Modules\Communication\Services\WhatsAppServiceV2::class);
        $this->windowService = app(\App\Modules\Communication\Services\ConversationWindowService::class);
    }

    private WhatsAppServiceV2 $whatsappService;
    private ConversationWindowService $windowService;

    public function windowStatus($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $phone = $customer->whatsapp_number ?? $customer->phone;

        if (!$phone) {
            return response()->json([
                'has_phone' => false,
                'within_window' => false,
                'message' => 'Customer has no WhatsApp number.',
            ]);
        }

        $phoneE164 = $this->windowService->formatToE164($phone);
        $withinWindow = $this->windowService->isWithinWindow($customer, $phoneE164);

        return response()->json([
            'has_phone' => true,
            'phone_e164' => $phoneE164,
            'within_window' => $withinWindow,
            'message' => $withinWindow
                ? 'Customer is within the 24-hour WhatsApp session window.'
                : 'Outside 24-hour window. Use approved template.',
        ]);
    }

    public function sendText(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);

        try {
            $message = $this->whatsappService->sendTextMessage($customer, $data['message']);

            return response()->json([
                'message' => 'Message sent successfully',
                'whatsapp_message' => $message,
            ], 201);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'outside 24-hour window')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'WINDOW_EXPIRED',
                ], 422);
            }

            $body = WhatsAppApiErrorResponse::fromThrowable($e, 'Failed to send message');
            $status = $e instanceof WhatsAppGraphApiException ? 422 : 500;

            return response()->json($body, $status);
        }
    }

    public function sendTemplate(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'template_name' => ['required', 'string'],
            'template_params' => ['nullable', 'array'],
            'language' => ['nullable', 'string'],
        ]);

        // Set default language if not provided
        $data['language'] = $data['language'] ?? 'en_US';

        $customer = Customer::findOrFail($data['customer_id']);
        $lead = isset($data['lead_id']) ? Lead::findOrFail($data['lead_id']) : null;

        // Verify template exists and is approved
        $template = WhatsAppTemplate::where('name', $data['template_name'])
            ->approved()
            ->first();

        if (!$template) {
            return response()->json([
                'message' => 'Template not found or not approved',
            ], 404);
        }

        try {
            $message = $this->whatsappService->sendTemplateMessage(
                $customer,
                $data['template_name'],
                $data['template_params'] ?? [],
                $data['language'] ?? 'en_US',
                $lead
            );

            return response()->json([
                'message' => 'Template message sent successfully',
                'whatsapp_message' => $message,
            ], 201);
        } catch (\Exception $e) {
            $body = WhatsAppApiErrorResponse::fromThrowable($e, 'Failed to send template message');
            $status = $e instanceof WhatsAppGraphApiException ? 422 : 500;

            return response()->json($body, $status);
        }
    }

    public function conversations(Request $request)
    {
        $query = WhatsAppConversation::with(['customer', 'messages' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(1);
        }]);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('phone')) {
            $phoneE164 = $this->windowService->formatToE164($request->phone);
            $query->where('customer_phone_e164', $phoneE164);
        }

        $conversations = $query->orderBy('last_inbound_at', 'desc')
            ->orderBy('last_outbound_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($conversations);
    }

    public function conversationMessages($id)
    {
        $conversation = WhatsAppConversation::with('customer')->findOrFail($id);

        $messages = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}

