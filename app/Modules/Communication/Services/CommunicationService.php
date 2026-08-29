<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\Communication;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\Communication\Jobs\SendCommunicationJob;
use Illuminate\Support\Facades\Bus;
use App\Models\ContactConsent;
use App\Services\SuppressionService;

class CommunicationService
{
    public function send(
        Customer $customer,
        ?Lead $lead,
        string $channel,
        string $direction,
        string $message,
        array $options = []
    ): Communication {
        $communication = Communication::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead?->id,
            'channel' => $channel,
            'direction' => $direction,
            'message' => $message,
            'status' => 'pending',
            'provider_payload' => $options ?: null,
        ]);

        $job = new SendCommunicationJob($communication->id, $options);
        // Default: run synchronously so messages are not stuck "pending" without `php artisan queue:work`.
        // Set COMMUNICATION_QUEUE_ASYNC=true in .env and run a queue worker to send asynchronously.
        // Read via config() so this still works once the config is cached.
        if (config('communication.queue_async')) {
            dispatch($job);
        } else {
            Bus::dispatchSync($job);
        }

        return $communication->fresh();
    }

    public function handleInbound(array $data, string $channel): ?Communication
    {
        $phone = $data['from'] ?? $data['phone'] ?? null;
        $message = $data['text'] ?? $data['message'] ?? $data['body'] ?? '';

        if (!$phone || !$message) {
            return null;
        }

        $suppression = app(SuppressionService::class);
        $isOptOut = $suppression->isOptOutKeyword($message);

        // An inbound message from an unknown number used to create a Customer,
        // which then matched the bulk-send filters - so replying STOP enrolled
        // the sender as a marketing target. Record the opt-out and log the
        // message, but do not create a contactable record.
        $customer = Customer::where('phone', $phone)->first();

        if (!$customer && $isOptOut) {
            $suppression->optOut($phone, ContactConsent::CHANNEL_SMS, 'inbound_keyword', $message);
            $suppression->optOut($phone, ContactConsent::CHANNEL_WHATSAPP, 'inbound_keyword', $message);

            return null;
        }

        if (!$customer) {
            $customer = Customer::create([
                'phone' => $phone,
                'name' => $phone,
            ]);
        }

        if ($isOptOut) {
            $suppression->optOut($phone, ContactConsent::CHANNEL_SMS, 'inbound_keyword', $message, $customer->id);
            $suppression->optOut($phone, ContactConsent::CHANNEL_WHATSAPP, 'inbound_keyword', $message, $customer->id);

            if ($customer->email) {
                $suppression->optOut($customer->email, ContactConsent::CHANNEL_EMAIL, 'inbound_keyword', $message, $customer->id);
            }
        }

        $communication = Communication::create([
            'customer_id' => $customer->id,
            'lead_id' => null,
            'channel' => $channel,
            'direction' => 'inbound',
            'message' => $message,
            'status' => 'delivered',
            'provider_payload' => $data,
        ]);

        // Dispatch notification event
        event(new \App\Events\NewMessageReceived($communication));

        return $communication;
    }
}


