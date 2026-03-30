<?php

namespace App\Events;

use App\Modules\Communication\Models\Communication;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Communication $communication)
    {}

    public function broadcastOn(): array
    {
        return [
            new Channel('notifications.all'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new-message-received';
    }

    public function broadcastWith(): array
    {
        return [
            'communication' => $this->communication->load('customer'),
            'message' => "New {$this->communication->channel} message from {$this->communication->customer->name}",
        ];
    }
}

