<?php

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Models\Communication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $communicationId,
        public array $options = []
    ) {}

    public function handle(): void
    {
        $communication = Communication::find($this->communicationId);

        if (!$communication) {
            return;
        }

        try {
            switch ($communication->channel) {
                case 'email':
                    $this->sendEmail($communication);
                    break;
                case 'whatsapp':
                    $this->sendWhatsApp($communication);
                    break;
                case 'sms':
                    $this->sendSMS($communication);
                    break;
            }

            $communication->update(['status' => 'sent']);
        } catch (\Exception $e) {
            Log::error('Communication send failed', [
                'communication_id' => $communication->id,
                'error' => $e->getMessage(),
            ]);
            $payload = is_array($communication->provider_payload) ? $communication->provider_payload : [];
            $payload['send_error'] = $e->getMessage();
            if ($communication->channel === 'whatsapp') {
                $payload['send_error_friendly'] = self::whatsappFriendlyError($e->getMessage());
            }
            $communication->update([
                'status' => 'failed',
                'provider_payload' => $payload,
            ]);
        }
    }

    /**
     * Plain-language hint for CRM users when Meta rejects a send (not “customer must allow the app”).
     */
    private static function whatsappFriendlyError(string $message): string
    {
        $m = strtolower($message);
        if (str_contains($m, 'credentials not configured')) {
            return 'WhatsApp is not fully connected on the server (missing Meta phone number ID or access token). An admin should check .env / settings.';
        }
        if (str_contains($m, 'template') || str_contains($m, '131047') || str_contains($m, 're-engagement') || str_contains($m, 'outside the 24')) {
            return 'WhatsApp lets you send a normal typed message once this person has recently messaged your business number. For the very first outreach, Meta requires an approved business template (created in Meta Business Suite), then the CRM can send it.';
        }
        if (str_contains($m, 'not in') && (str_contains($m, 'allow') || str_contains($m, 'whitelist'))) {
            return 'This number may not be on Meta’s test allow list (sandbox). Add it in Meta Developer → WhatsApp → API setup, or use a production app.';
        }
        if (str_contains($m, 'invalid') && str_contains($m, 'oauth')) {
            return 'Meta rejected the access token. An admin should renew the WhatsApp access token in Meta and update server settings.';
        }
        if (str_contains($m, '132012') || str_contains($m, 'parameter format does not match')) {
            return 'The send payload did not match this template in Meta (language code, named vs numbered variables, or missing header/body parameters). Sync templates in CRM, pick the exact template language, and pass template_params if the template has {{1}} or {{name}} placeholders.';
        }

        return 'WhatsApp could not send. Check the number includes country code (e.g. 447…). If it still fails, see the server log or ask an admin to verify the Meta app and token.';
    }

    private function sendEmail(Communication $communication): void
    {
        $to = $this->options['to_email'] ?? $communication->customer->email;
        $subject = $this->options['subject'] ?? $communication->provider_payload['subject'] ?? 'Message from Switch & Save CRM';
        \App\Services\MailConfigFromDatabase::apply();
        Mail::raw($communication->message, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    private function sendWhatsApp(Communication $communication): void
    {
        try {
            $whatsappService = app(\App\Modules\Communication\Services\WhatsAppServiceV2::class);
            $customer = $communication->customer;
            if (!$customer) {
                throw new \Exception('Customer not found for WhatsApp communication.');
            }

            if (!empty($communication->media_url)) {
                throw new \Exception('Media sending is not enabled in the unified WhatsApp V2 flow yet.');
            }

            $templateName = trim((string) ($this->options['template_name'] ?? ''));
            if ($templateName !== '') {
                $waMessage = $whatsappService->sendTemplateMessage(
                    $customer,
                    $templateName,
                    $this->options['template_params'] ?? [],
                    $this->options['language'] ?? 'en_US',
                );
                $result = [
                    'message_id' => $waMessage->meta_wamid,
                    'response' => $waMessage->meta_payload_json,
                ];
            } else {
                $waMessage = $whatsappService->sendTextMessage($customer, $communication->message);
                $result = [
                    'message_id' => $waMessage->meta_wamid,
                    'response' => $waMessage->meta_payload_json,
                ];
            }

            // Update communication with provider response
            $communication->update([
                'status' => 'sent',
                'provider_payload' => $result['response'] ?? null,
            ]);

            Log::info('WhatsApp message sent successfully', [
                'communication_id' => $communication->id,
                'message_id' => $result['message_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'communication_id' => $communication->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function sendSMS(Communication $communication): void
    {
        try {
            $smsService = app(\App\Services\SmsService::class);
            
            // Get phone number from options or customer
            $phoneNumber = $this->options['to_number'] 
                ?? $communication->customer->phone;

            if (!$phoneNumber) {
                Log::warning('SMS skipped — no phone number', [
                    'communication_id' => $communication->id
                ]);
                $communication->update(['status' => 'failed']);
                return;
            }

            $result = $smsService->send($phoneNumber, $communication->message);

            if ($result['success']) {
                // Update communication with provider response
                $communication->update([
                    'status' => 'sent',
                    'provider_payload' => $result['response'],
                ]);

                Log::info('SMS sent successfully', [
                    'communication_id' => $communication->id,
                    'phone' => $phoneNumber,
                ]);
            } else {
                $communication->update([
                    'status' => 'failed',
                    'provider_payload' => $result['response'],
                ]);

                Log::error('SMS send failed', [
                    'communication_id' => $communication->id,
                    'phone' => $phoneNumber,
                    'error' => $result['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SMS send exception', [
                'communication_id' => $communication->id,
                'error' => $e->getMessage(),
            ]);
            $communication->update(['status' => 'failed']);
            throw $e;
        }
    }
}


