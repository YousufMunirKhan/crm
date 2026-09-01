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
     * Meta requires example text for BODY variables. Adds body_text examples for {{1}}, {{2}}, …
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    public function enrichComponentsWithVariableExamples(array $components): array
    {
        $out = [];
        foreach ($components as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (strtoupper((string) ($c['type'] ?? '')) !== 'BODY') {
                $out[] = $c;

                continue;
            }

            $text = (string) ($c['text'] ?? '');
            if ($text === '' || !preg_match_all('/\{\{(\d+)\}\}/', $text, $m)) {
                $out[] = $c;

                continue;
            }

            $nums = array_unique(array_map('intval', $m[1]));
            sort($nums, SORT_NUMERIC);
            $row = [];
            foreach ($nums as $n) {
                $row[] = 'Sample_' . $n;
            }
            $c['example'] = ['body_text' => [$row]];
            $out[] = $c;
        }

        return $out;
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

        $components = $this->enrichComponentsWithVariableExamples($data['components'] ?? []);

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
            $template->update([
                'rejection_reason' => $e->getMessage(),
            ]);
            throw $e;
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

            // Request full components (including `example` / body_text_named_params) or sends hit #132012.
            $response = $this->client->listTemplates($settings->waba_id, [
                'fields' => 'name,status,language,category,components,parameter_format,id,rejection_reason',
                'limit' => '200',
            ]);
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
                        'parameter_format' => isset($metaTemplate['parameter_format'])
                            ? strtoupper((string) $metaTemplate['parameter_format'])
                            : null,
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
            $this->logSyncFailure($e->getMessage());

            return [
                'synced' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Logs a sync failure at most once an hour while the cause does not change.
     *
     * This sync runs every fifteen minutes. The access token expired on 2 April
     * and has been rejected every run since, so by September the log held five
     * months of the same OAuthException 190 - roughly fifteen thousand lines
     * saying one thing. That is not a log, it is cover: a dead Claude model sat
     * unnoticed in this same file for months because nobody could face reading
     * it, and anything genuinely new arriving today would be buried the same
     * way.
     *
     * An expired credential is a real failure and still gets logged - just once
     * per hour per distinct message, so a second, different failure is visible
     * beside it rather than underneath it.
     */
    private function logSyncFailure(string $message): void
    {
        $key = 'whatsapp:sync-failure:'.md5($message);

        if (\Illuminate\Support\Facades\Cache::get($key)) {
            return;
        }

        \Illuminate\Support\Facades\Cache::put($key, true, now()->addHour());

        Log::error('Failed to sync templates from Meta', [
            'error' => $message,
            'note' => 'Repeats of this exact error are suppressed for an hour.',
        ]);
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

