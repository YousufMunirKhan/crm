<?php

namespace App\Mail;

use App\Support\PdfDocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

class CommissionMonthlyAdminMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $monthLabel,
        public string $introduction,
        public array $userBlocks,
        public array $overallTotals,
        public string $pdfFileName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Commission report — {$this->monthLabel} — {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.commission_monthly_admin',
            with: [
                'companyName' => $this->companyName,
                'monthLabel' => $this->monthLabel,
                'introduction' => $this->introduction,
                'userBlocks' => $this->userBlocks,
                'overallTotals' => $this->overallTotals,
                'pdf_filename' => $this->pdfFileName,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $generatedAt = Carbon::now()->toDayDateTimeString();

        $brand = PdfDocumentBranding::package();

        $pdf = Pdf::loadView('commission.pdf_monthly_full', [
            'companyName' => $this->companyName,
            'monthLabel' => $this->monthLabel,
            'userBlocks' => $this->userBlocks,
            'overallTotals' => $this->overallTotals,
            'generatedAt' => $generatedAt,
            'logoUrl' => $brand['logoUrl'],
            'settings' => $brand['settings'],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('enable-local-file-access', true);

        return [
            Attachment::fromData(fn () => $pdf->output(), $this->pdfFileName)->withMime('application/pdf'),
        ];
    }
}
