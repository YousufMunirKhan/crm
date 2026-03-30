<?php

namespace App\Events;

use App\Modules\CRM\Models\Lead;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FollowUpReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Lead $lead)
    {}

    public function broadcastOn(): array
    {
        return [
            new Channel('notifications.' . $this->lead->assigned_to),
        ];
    }

    public function broadcastAs(): string
    {
        return 'followup-reminder';
    }

    public function broadcastWith(): array
    {
        return [
            'lead' => $this->lead->load(['customer', 'assignee']),
            'message' => "Follow-up reminder: {$this->lead->customer->name}",
        ];
    }
}

