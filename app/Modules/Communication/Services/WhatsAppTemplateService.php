<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

class WhatsAppTemplateService
{
    public function __construct()
    {
        $this->client = new WhatsAppCloudClient();
    }

    private WhatsAppCloudClient $client;

    /**
     * Legacy whatsapp_templates.message column is NOT NULL — derive from Meta BODY component.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    private function messageFromComponents(array $components, ?string $fallbackName = null): string
    {
        foreach ($components as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === 'BODY' && isset($component['text'])) {
                return (string) $component['text'];
            }
        }

        $fallback = $fallbackName !== null && $fallbackName !== ''
            ? sprintf('[%s]', $fallbackName)
            : '[WhatsApp template]';

        return $fallback;
    }

    /**
     * Create template locally and submit to Meta
     */
    public function createTemplate(array $data): WhatsAppTemplate
    {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled || !$settings->waba_id) {
            throw new \Exception('WhatsApp is not enabled or WABA ID not configured');
        }

        $components = $data['components'] ?? [];

        // Create template locally
        $template = WhatsAppTemplate::create([
            'name' => $data['name'],
            'category' => $data['category'],
            'language' => $data['language'] ?? 'en_US',
            'components_json' => $components,
            'message' => $this->messageFromComponents($components, $data['name'] ?? null),
            'status' => 'PENDING',
        ]);

        try {
            // Submit to Meta
            $this->client->setAccessToken($settings->access_token);
            $this->client->setGraphVersion($settings->graph_version);

            $payload = [
                'name' => $template->name,
                'category' => $template->category,
                'language' => $template->language,
                'components' => $template->components_json,
            ];

            $response = $this->client->createTemplate($settings->waba_id, $payload);

            // Update with Meta template ID
            $template->update([
                'meta_template_id' => $response['id'] ?? null,
                'status' => 'PENDING', // Will be updated by sync job
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to submit template to Meta', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            // Template remains in PENDING status
        }

        return $template;
    }

    /**
     * Sync templates from Meta (update approval status)
     */
    public function syncTemplates(): array
    {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled || !$settings->waba_id) {
            return ['synced' => 0, 'errors' => []];
        }

        try {
            $this->client->setAccessToken($settings->access_token);
            $this->client->setGraphVersion($settings->graph_version);

            $response = $this->client->listTemplates($settings->waba_id);
            $metaTemplates = $response['data'] ?? [];

            $synced = 0;
            $errors = [];

            foreach ($metaTemplates as $metaTemplate) {
                try {
                    $template = WhatsAppTemplate::where('name', $metaTemplate['name'])
                        ->orWhere('meta_template_id', $metaTemplate['id'])
                        ->first();

                    $status = strtoupper($metaTemplate['status'] ?? 'PENDING');
                    $components = $metaTemplate['components'] ?? [];
                    $payload = [
                        'meta_template_id' => $metaTemplate['id'] ?? null,
                        'name' => $metaTemplate['name'] ?? null,
                        'category' => strtoupper($metaTemplate['category'] ?? 'TRANSACTIONAL'),
                        'language' => $metaTemplate['language'] ?? 'en_US',
                        'status' => $status,
                        'rejection_reason' => $metaTemplate['rejection_reason'] ?? null,
                        'components_json' => $components,
                        'message' => $this->messageFromComponents($components, $metaTemplate['name'] ?? null),
                    ];

                    if ($template) {
                        $template->update($payload);
                    } else {
                        // Create local record for templates that were created directly in Meta.
                        WhatsAppTemplate::create($payload);
                    }
                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to sync template {$metaTemplate['name']}: " . $e->getMessage();
                }
            }

            return [
                'synced' => $synced,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync templates from Meta', [
                'error' => $e->getMessage(),
            ]);
            return [
                'synced' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Resubmit rejected template
     */
    public function resubmitTemplate(WhatsAppTemplate $template): WhatsAppTemplate
    {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled || !$settings->waba_id) {
            throw new \Exception('WhatsApp is not enabled or WABA ID not configured');
        }

        try {
            $this->client->setAccessToken($settings->access_token);
            $this->client->setGraphVersion($settings->graph_version);

            $payload = [
                'name' => $template->name,
                'category' => $template->category,
                'language' => $template->language,
                'components' => $template->components_json,
            ];

            $response = $this->client->createTemplate($settings->waba_id, $payload);

            $template->update([
                'meta_template_id' => $response['id'] ?? null,
                'status' => 'PENDING',
                'rejection_reason' => null,
            ]);

            return $template;
        } catch (\Exception $e) {
            Log::error('Failed to resubmit template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

