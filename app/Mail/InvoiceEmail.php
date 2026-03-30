<?php

namespace App\Mail;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Settings\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $customMessage,
        public string $companyName,
        public string $logoUrl = '',
        public ?string $logoPath = null,
        public string $customerName = 'Customer',
        public string $socialFacebook = '',
        public string $socialTwitter = '',
        public string $socialLinkedIn = '',
        public string $socialInstagram = '',
        public string $socialTikTok = '',
        public string $companyWebsite = '',
        public string $companyPhone = '',
        public string $companyAddress = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Invoice {$this->invoice->invoice_number} - {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'customMessage' => $this->customMessage,
                'companyName' => $this->companyName,
                'logoUrl' => $this->logoUrl,
                'logoPath' => $this->logoPath,
                'customerName' => $this->customerName,
                'socialFacebook' => $this->socialFacebook,
                'socialTwitter' => $this->socialTwitter,
                'socialLinkedIn' => $this->socialLinkedIn,
                'socialInstagram' => $this->socialInstagram,
                'socialTikTok' => $this->socialTikTok,
                'companyWebsite' => $this->companyWebsite,
                'companyPhone' => $this->companyPhone,
                'companyAddress' => $this->companyAddress,
            ]
        );
    }

    public function attachments(): array
    {
        $invoiceService = app(\App\Modules\Invoice\Services\InvoiceService::class);
        $pdf = $invoiceService->generatePDF($this->invoice);

        return [
            Attachment::fromData(fn () => $pdf->output(), "invoice-{$this->invoice->invoice_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
