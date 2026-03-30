<?php

namespace App\Events;

use App\Modules\Ticket\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewTicketCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket)
    {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('notifications.all')];
        
        if ($this->ticket->assigned_to) {
            $channels[] = new Channel('notifications.' . $this->ticket->assigned_to);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'new-ticket-created';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => $this->ticket->load(['customer', 'assignee']),
            'message' => "New ticket created: {$this->ticket->subject}",
        ];
    }
}

