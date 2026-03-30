<?php

namespace App\Mail;

use App\Modules\Settings\Models\Setting;
use App\Modules\Ticket\Models\Ticket;
use App\Services\CrmPublicUrl;
use App\Modules\Ticket\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCommentMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $companyName;

    public readonly string $ticketUrl;

    public function __construct(
        public Ticket $ticket,
        public TicketMessage $ticketMessage,
    ) {
        $this->ticket->loadMissing(['customer', 'creator', 'assignee', 'attachments']);
        $this->ticketMessage->loadMissing('user');
        $this->companyName = Setting::where('key', 'company_name')->first()?->value ?? config('app.name', 'CRM');
        $this->ticketUrl = CrmPublicUrl::ticket((int) $this->ticket->id);
    }

    public function envelope(): Envelope
    {
        $prefix = $this->ticketMessage->is_internal ? '[Internal note] ' : '';
        return new Envelope(
            subject: "{$prefix}New comment on {$this->ticket->ticket_number}",
        );
    }

    public function content(): Content
    {
        $logoUrl = Setting::where('key', 'logo_url')->first()?->value ?? '';
        if ($logoUrl && !str_starts_with($logoUrl, 'http')) {
            $logoUrl = rtrim(config('app.url'), '/') . $logoUrl;
        }

        return new Content(
            view: 'emails.ticket-comment',
            with: [
                'ticket' => $this->ticket,
                'ticketMessage' => $this->ticketMessage,
                'companyName' => $this->companyName,
                'ticketUrl' => $this->ticketUrl,
                'logoUrl' => $logoUrl,
            ],
        );
    }
}
