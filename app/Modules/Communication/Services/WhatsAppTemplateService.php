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
     * Create template locally and submit to Meta
     */
    public function createTemplate(array $data): WhatsAppTemplate
    {
        $settings = WhatsAppSetting::getActive();
        if (!$settings || !$settings->is_enabled || !$settings->waba_id) {
            throw new \Exception('WhatsApp is not enabled or WABA ID not configured');
        }

        // Create template locally
        $template = WhatsAppTemplate::create([
            'name' => $data['name'],
            'category' => $data['category'],
            'language' => $data['language'] ?? 'en_US',
            'components_json' => $data['components'] ?? [],
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

                    if ($template) {
                        $status = strtoupper($metaTemplate['status'] ?? 'PENDING');
                        $template->update([
                            'meta_template_id' => $metaTemplate['id'],
                            'status' => $status,
                            'rejection_reason' => $metaTemplate['rejection_reason'] ?? null,
                        ]);
                        $synced++;
                    }
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

