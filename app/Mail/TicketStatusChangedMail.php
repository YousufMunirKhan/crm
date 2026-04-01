<?php

namespace App\Mail;

use App\Modules\Settings\Models\Setting;
use App\Modules\Ticket\Models\Ticket;
use App\Services\CrmPublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $companyName;

    public readonly string $ticketUrl;

    public function __construct(
        public Ticket $ticket,
        public string $previousStatus,
        public ?int $changedByUserId,
    ) {
        $this->ticket->loadMissing(['customer', 'creator', 'assignee', 'assignees', 'attachments']);
        $this->companyName = Setting::where('key', 'company_name')->first()?->value ?? config('app.name', 'CRM');
        $this->ticketUrl = CrmPublicUrl::ticket((int) $this->ticket->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->ticket_number}] Status: {$this->humanStatus($this->previousStatus)} → {$this->humanStatus($this->ticket->status)}",
        );
    }

    public function content(): Content
    {
        $logoUrl = Setting::where('key', 'logo_url')->first()?->value ?? '';
        if ($logoUrl && !str_starts_with($logoUrl, 'http')) {
            $logoUrl = rtrim(config('app.url'), '/') . $logoUrl;
        }

        $actor = $this->changedByUserId ? \App\Models\User::find($this->changedByUserId) : null;

        return new Content(
            view: 'emails.ticket-status-changed',
            with: [
                'ticket' => $this->ticket,
                'previousStatus' => $this->previousStatus,
                'previousStatusLabel' => $this->humanStatus($this->previousStatus),
                'newStatusLabel' => $this->humanStatus($this->ticket->status),
                'changedByName' => $actor?->name ?? 'A team member',
                'companyName' => $this->companyName,
                'ticketUrl' => $this->ticketUrl,
                'logoUrl' => $logoUrl,
            ],
        );
    }

    private function humanStatus(?string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'in_progress' => 'Working',
            'on_hold' => 'On Hold',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => $status ?? '—',
        };
    }
}
