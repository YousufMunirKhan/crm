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

        $this->ticket->loadMissing('assignees');
        $ids = $this->ticket->assignees->pluck('id')->all();
        if ($ids === [] && $this->ticket->assigned_to) {
            $ids = [(int) $this->ticket->assigned_to];
        }
        foreach (array_unique($ids) as $userId) {
            $channels[] = new Channel('notifications.' . $userId);
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
            'ticket' => $this->ticket->load(['customer', 'assignee', 'assignees']),
            'message' => "New ticket created: {$this->ticket->subject}",
        ];
    }
}

