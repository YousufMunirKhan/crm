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
            $communication->update(['status' => 'failed']);
        }
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
            $whatsappService = app(\App\Modules\Communication\Services\WhatsAppService::class);
            
            // Get phone number from options or customer
            $toNumber = $this->options['to_number'] 
                ?? $communication->customer->whatsapp_number 
                ?? $communication->customer->phone;

            $result = $whatsappService->sendMessage(
                $toNumber,
                $communication->message,
                null,
                $communication->media_url,
                $communication->media_type
            );

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


