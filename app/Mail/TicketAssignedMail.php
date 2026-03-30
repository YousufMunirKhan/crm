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

class TicketAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $companyName;

    public readonly string $ticketUrl;

    public function __construct(public Ticket $ticket)
    {
        $this->ticket->loadMissing(['customer', 'creator', 'assignee', 'attachments']);
        $this->companyName = Setting::where('key', 'company_name')->first()?->value ?? config('app.name', 'CRM');
        $this->ticketUrl = CrmPublicUrl::ticket((int) $this->ticket->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->ticket_number}] Ticket assigned to you — {$this->ticket->subject}",
        );
    }

    public function content(): Content
    {
        $logoUrl = Setting::where('key', 'logo_url')->first()?->value ?? '';
        if ($logoUrl && !str_starts_with($logoUrl, 'http')) {
            $logoUrl = rtrim(config('app.url'), '/') . $logoUrl;
        }

        return new Content(
            view: 'emails.ticket-assigned',
            with: [
                'ticket' => $this->ticket,
                'companyName' => $this->companyName,
                'ticketUrl' => $this->ticketUrl,
                'logoUrl' => $logoUrl,
            ],
        );
    }
}
