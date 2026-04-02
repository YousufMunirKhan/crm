<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\Communication;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\Communication\Jobs\SendCommunicationJob;
use Illuminate\Support\Facades\Bus;

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
        // Set COMMUNICATION_QUEUE_ASYNC=true in .env and run a queue worker if you prefer async sends.
        if (filter_var(env('COMMUNICATION_QUEUE_ASYNC', false), FILTER_VALIDATE_BOOLEAN)) {
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

        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            ['name' => $phone]
        );

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


