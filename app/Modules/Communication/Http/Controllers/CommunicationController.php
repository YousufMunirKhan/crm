<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\Communication;
use App\Modules\Communication\Jobs\SendCommunicationJob;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Communication\Services\ConversationWindowService;
use App\Modules\Communication\Services\WhatsAppServiceV2;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function __construct(
        private CommunicationService $communicationService,
        private WhatsAppServiceV2 $whatsAppServiceV2,
        private ConversationWindowService $conversationWindowService,
    ) {}

    public function index(Request $request)
    {
        $query = Communication::with(['customer', 'lead']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        $communications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($communications);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'channel' => ['required', 'in:whatsapp,email,sms'],
            'message' => ['nullable', 'string'],
            'to_number' => ['nullable', 'string'], // Custom WhatsApp/SMS number
            'to_email' => ['nullable', 'email'], // Custom email address
            'subject' => ['nullable', 'string'], // For email subject
            'template_name' => ['nullable', 'string'],
            'template_params' => ['nullable', 'array'],
            'language' => ['nullable', 'string'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $lead = isset($data['lead_id']) ? Lead::findOrFail($data['lead_id']) : null;

        if ($data['channel'] === 'whatsapp') {
            $toNumber = $data['to_number'] ?? $customer->whatsapp_number ?? $customer->phone;
            if (!$toNumber) {
                return response()->json(['message' => 'Customer has no WhatsApp number configured.'], 422);
            }

            $phoneE164 = $this->conversationWindowService->formatToE164($toNumber);
            $withinWindow = $this->conversationWindowService->isWithinWindow($customer, $phoneE164);
            $templateName = trim((string) ($data['template_name'] ?? ''));
            $hasTemplate = $templateName !== '';

            if (!$withinWindow && !$hasTemplate) {
                return response()->json([
                    'message' => 'Customer is outside the 24-hour WhatsApp window. Use an approved WhatsApp template.',
                    'error' => 'WINDOW_EXPIRED',
                ], 422);
            }

            if ($withinWindow && empty(trim((string) ($data['message'] ?? ''))) && !$hasTemplate) {
                return response()->json(['message' => 'Please enter a message.'], 422);
            }

            try {
                if ($hasTemplate) {
                    $waMessage = $this->whatsAppServiceV2->sendTemplateMessage(
                        $customer,
                        $templateName,
                        $data['template_params'] ?? [],
                        $data['language'] ?? 'en_US'
                    );
                    $storedMessage = '[Template] ' . $templateName;
                } else {
                    $waMessage = $this->whatsAppServiceV2->sendTextMessage($customer, trim((string) $data['message']));
                    $storedMessage = trim((string) $data['message']);
                }

                $communication = Communication::create([
                    'customer_id' => $customer->id,
                    'lead_id' => $lead?->id,
                    'channel' => 'whatsapp',
                    'direction' => 'outbound',
                    'message' => $storedMessage,
                    'status' => 'sent',
                    'provider_payload' => [
                        'via' => 'whatsapp_v2',
                        'window_status' => $withinWindow ? 'open' : 'closed',
                        'template_name' => $hasTemplate ? $templateName : null,
                        'meta_wamid' => $waMessage->meta_wamid ?? null,
                    ],
                ]);

                return response()->json($communication->load(['customer', 'lead']), 201);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to send WhatsApp message: ' . $e->getMessage(),
                    'hint' => SendCommunicationJob::whatsappMetaUserHint($e->getMessage()),
                ], 500);
            }
        }

        // Determine recipient based on channel and custom values
        if (empty(trim((string) ($data['message'] ?? '')))) {
            return response()->json(['message' => 'Message is required.'], 422);
        }

        $options = [];
        if ($data['channel'] === 'whatsapp' && !empty($data['to_number'])) {
            $options['to_number'] = $data['to_number'];
        } elseif ($data['channel'] === 'sms' && !empty($data['to_number'])) {
            $options['to_number'] = $data['to_number'];
        } elseif ($data['channel'] === 'email' && !empty($data['to_email'])) {
            $options['to_email'] = $data['to_email'];
        }
        if (!empty($data['subject'])) {
            $options['subject'] = $data['subject'];
        }

        $communication = $this->communicationService->send(
            $customer,
            $lead,
            $data['channel'],
            'outbound',
            trim((string) $data['message']),
            $options
        );

        return response()->json($communication->load(['customer', 'lead']), 201);
    }

    public function show($id)
    {
        $communication = Communication::with(['customer', 'lead'])->findOrFail($id);
        return response()->json($communication);
    }
}


