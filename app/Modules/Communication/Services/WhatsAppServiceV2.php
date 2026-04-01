<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\WhatsAppConversation;
use App\Modules\Communication\Models\WhatsAppMessage;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Models\WhatsAppTemplate;
use App\Modules\CRM\Models\Customer;
use Illuminate\Support\Facades\Log;

class WhatsAppServiceV2
{
    public function __construct()
    {
        $this->client = new WhatsAppCloudClient();
        $this->windowService = new ConversationWindowService();
    }

    private WhatsAppCloudClient $client;
    private ConversationWindowService $windowService;

    /**
     * Send text message (only within 24h window)
     */
    public function sendTextMessage(Customer $customer, string $message): WhatsAppMessage
    {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled) {
            throw new \Exception('WhatsApp is not enabled or configured');
        }

        $phoneE164 = $this->windowService->formatToE164(
            $customer->whatsapp_number ?? $customer->phone
        );

        // Check if within window
        if (!$this->windowService->isWithinWindow($customer, $phoneE164)) {
            throw new \Exception('Customer is outside 24-hour window. Please use an approved template.');
        }

        $conversation = $this->windowService->getOrCreateConversation($customer, $phoneE164);

        // Build payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneE164,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ];

        // Send via API
        $this->client->setAccessToken($settings->access_token);
        $this->client->setGraphVersion($settings->graph_version);
        
        $response = $this->client->sendMessage($settings->phone_number_id, $payload);

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'OUT',
            'to_e164' => $phoneE164,
            'from_e164' => $settings->phone_number_id,
            'type' => 'text',
            'body_text' => $message,
            'meta_wamid' => $response['messages'][0]['id'] ?? null,
            'status' => 'sent',
            'meta_payload_json' => $response,
        ]);

        // Update conversation
        $this->windowService->updateWindowAfterOutbound($phoneE164, $customer);

        return $whatsappMessage;
    }

    /**
     * Build Cloud API template.components from synced template JSON + user params.
     * Returns null if the template has no variable placeholders (Meta: omit "components").
     *
     * @param  array<int, array<string, mixed>>|null  $componentsJson
     * @return array<int, array<string, mixed>>|null
     */
    private function buildTemplateComponentsForSend(?array $componentsJson, array $templateParams): ?array
    {
        if (empty($componentsJson)) {
            return null;
        }

        $byType = [];
        foreach ($componentsJson as $c) {
            $t = strtoupper((string) ($c['type'] ?? ''));
            if ($t === 'HEADER' || $t === 'BODY') {
                $byType[$t] = $c;
            }
        }

        $queue = array_values($templateParams);
        $out = [];

        foreach (['HEADER', 'BODY'] as $slot) {
            if (!isset($byType[$slot])) {
                continue;
            }
            $c = $byType[$slot];
            $apiType = $slot === 'HEADER' ? 'header' : 'body';

            if ($slot === 'HEADER') {
                $format = strtoupper((string) ($c['format'] ?? 'TEXT'));
                if ($format !== 'TEXT') {
                    continue;
                }
            }

            $text = (string) ($c['text'] ?? '');
            if ($text === '' || !preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            $parameters = $this->placeholderRowsToParameters($matches, $templateParams, $queue);
            $out[] = [
                'type' => $apiType,
                'parameters' => $parameters,
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<int, array<int, string>>  $pregSetOrder  matches from PREG_SET_ORDER
     * @param  array<int|string, mixed>  $templateParams
     * @param  array<int, mixed>  $queue  consumed for positional params (by appearance order)
     * @return array<int, array<string, mixed>>
     */
    private function placeholderRowsToParameters(array $pregSetOrder, array $templateParams, array &$queue): array
    {
        $placeholders = array_map(static fn ($m) => trim($m[1]), $pregSetOrder);

        $hasNumeric = false;
        $hasNamed = false;
        foreach ($placeholders as $ph) {
            if (preg_match('/^\d+$/', $ph)) {
                $hasNumeric = true;
            } else {
                $hasNamed = true;
            }
        }

        if ($hasNumeric && $hasNamed) {
            throw new \InvalidArgumentException('Template mixes {{1}}-style and {{name}}-style placeholders in the same component; fix the template in Meta or send valid parameters.');
        }

        $parameters = [];
        if ($hasNamed) {
            foreach ($placeholders as $name) {
                $val = $templateParams[$name]
                    ?? $templateParams[mb_strtolower($name)]
                    ?? $templateParams[mb_strtoupper($name)]
                    ?? '';
                $parameters[] = [
                    'type' => 'text',
                    'parameter_name' => $name,
                    'text' => (string) $val,
                ];
            }

            return $parameters;
        }

        foreach ($placeholders as $_) {
            $val = array_shift($queue);
            $parameters[] = [
                'type' => 'text',
                'text' => (string) ($val ?? ''),
            ];
        }

        return $parameters;
    }

    /**
     * Send template message (can be sent outside window)
     */
    public function sendTemplateMessage(
        Customer $customer,
        string $templateName,
        array $templateParams = [],
        ?string $language = 'en_US'
    ): WhatsAppMessage {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled) {
            throw new \Exception('WhatsApp is not enabled or configured');
        }

        $phoneE164 = $this->windowService->formatToE164(
            $customer->whatsapp_number ?? $customer->phone
        );

        $conversation = $this->windowService->getOrCreateConversation($customer, $phoneE164);

        $templateModel = WhatsAppTemplate::where('name', $templateName)->first();
        $lang = $templateModel?->language ?: $language ?: 'en_US';
        $componentsJson = is_array($templateModel?->components_json) ? $templateModel->components_json : null;

        $components = $this->buildTemplateComponentsForSend($componentsJson, $templateParams);

        $templateBlock = [
            'name' => $templateName,
            'language' => ['code' => $lang],
        ];
        if ($components !== null) {
            $templateBlock['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneE164,
            'type' => 'template',
            'template' => $templateBlock,
        ];

        // Send via API
        $this->client->setAccessToken($settings->access_token);
        $this->client->setGraphVersion($settings->graph_version);
        
        $response = $this->client->sendMessage($settings->phone_number_id, $payload);

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'OUT',
            'to_e164' => $phoneE164,
            'from_e164' => $settings->phone_number_id,
            'type' => 'template',
            'template_name' => $templateName,
            'template_params_json' => $templateParams,
            'meta_wamid' => $response['messages'][0]['id'] ?? null,
            'status' => 'sent',
            'meta_payload_json' => $response,
        ]);

        // Update conversation
        $this->windowService->updateWindowAfterOutbound($phoneE164, $customer);

        return $whatsappMessage;
    }

    /**
     * Send media message (image/video/document) with optional caption.
     */
    public function sendMediaMessage(
        Customer $customer,
        string $mediaType,
        string $mediaUrl,
        ?string $caption = null
    ): WhatsAppMessage {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled) {
            throw new \Exception('WhatsApp is not enabled or configured');
        }

        $allowedTypes = ['image', 'video', 'document'];
        if (!in_array($mediaType, $allowedTypes, true)) {
            throw new \Exception('Unsupported media type for WhatsApp: ' . $mediaType);
        }

        $phoneE164 = $this->windowService->formatToE164(
            $customer->whatsapp_number ?? $customer->phone
        );
        $conversation = $this->windowService->getOrCreateConversation($customer, $phoneE164);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneE164,
            'type' => $mediaType,
            $mediaType => [
                'link' => $mediaUrl,
            ],
        ];

        if ($caption) {
            $payload[$mediaType]['caption'] = $caption;
        }

        $this->client->setAccessToken($settings->access_token);
        $this->client->setGraphVersion($settings->graph_version);
        $response = $this->client->sendMessage($settings->phone_number_id, $payload);

        $whatsappMessage = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'OUT',
            'to_e164' => $phoneE164,
            'from_e164' => $settings->phone_number_id,
            'type' => $mediaType,
            'body_text' => $caption,
            'meta_wamid' => $response['messages'][0]['id'] ?? null,
            'status' => 'sent',
            'meta_payload_json' => $response,
        ]);

        $this->windowService->updateWindowAfterOutbound($phoneE164, $customer);

        return $whatsappMessage;
    }

    public function getAddPhoneNumberInfo(string $phone): array
    {
        $formatted = ltrim($this->windowService->formatToE164($phone), '+');
        $appId = env('WHATSAPP_APP_ID', '968377435537226');
        $addUrl = "https://developers.facebook.com/apps/{$appId}/whatsapp-business/cloud-api/get-started";

        return [
            'formatted_number' => $formatted,
            'add_url' => $addUrl,
            'instructions' => "Add this number ({$formatted}) to Meta Business Account allowed list",
        ];
    }

    /**
     * Handle inbound message from webhook
     */
    public function handleInboundMessage(array $webhookData): ?WhatsAppMessage
    {
        try {
            $entry = $webhookData['entry'][0] ?? null;
            if (!$entry) {
                return null;
            }

            $changes = $entry['changes'][0] ?? null;
            if (!$changes || ($changes['field'] ?? null) !== 'messages') {
                return null;
            }

            $value = $changes['value'] ?? [];
            $message = $value['messages'][0] ?? null;
            $contacts = $value['contacts'][0] ?? null;

            if (!$message) {
                return null;
            }

            $from = $message['from'] ?? null;
            $wamid = $message['id'] ?? null;
            $type = $message['type'] ?? 'text';
            $text = $message['text']['body'] ?? '';
            $timestamp = $message['timestamp'] ?? null;

            if (!$from || !$wamid) {
                return null;
            }

            // Find or create customer
            $customer = Customer::where('whatsapp_number', $from)
                ->orWhere('phone', $from)
                ->first();

            // Get or create conversation
            $conversation = $this->windowService->updateWindowAfterInbound($from, $customer);

            // Check if message already exists (idempotency)
            $existingMessage = WhatsAppMessage::where('meta_wamid', $wamid)->first();
            if ($existingMessage) {
                return $existingMessage;
            }

            // Create message
            $whatsappMessage = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'IN',
                'from_e164' => $from,
                'to_e164' => $value['metadata']['phone_number_id'] ?? null,
                'type' => $type,
                'body_text' => $text,
                'meta_wamid' => $wamid,
                'status' => 'delivered',
                'meta_payload_json' => $message,
            ]);

            return $whatsappMessage;
        } catch (\Exception $e) {
            Log::error('Failed to handle inbound WhatsApp message', [
                'error' => $e->getMessage(),
                'data' => $webhookData,
            ]);
            return null;
        }
    }

    /**
     * Handle status update from webhook
     */
    public function handleStatusUpdate(array $webhookData): void
    {
        try {
            $entry = $webhookData['entry'][0] ?? null;
            if (!$entry) {
                return;
            }

            $changes = $entry['changes'][0] ?? null;
            if (!$changes || ($changes['field'] ?? null) !== 'messages') {
                return;
            }

            $value = $changes['value'] ?? [];
            $statuses = $value['statuses'] ?? [];

            foreach ($statuses as $status) {
                $wamid = $status['id'] ?? null;
                $statusValue = $status['status'] ?? null;

                if (!$wamid || !$statusValue) {
                    continue;
                }

                $message = WhatsAppMessage::where('meta_wamid', $wamid)->first();
                if ($message) {
                    $message->update([
                        'status' => strtolower($statusValue),
                        'meta_payload_json' => array_merge(
                            $message->meta_payload_json ?? [],
                            ['status_update' => $status]
                        ),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle WhatsApp status update', [
                'error' => $e->getMessage(),
                'data' => $webhookData,
            ]);
        }
    }
}

