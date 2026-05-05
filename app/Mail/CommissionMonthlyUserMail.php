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
use Illuminate\Support\Str;

class CommissionMonthlyUserMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $monthLabel,
        /** @var string Calendar month Y-m (file naming) */
        public string $yearMonth,
        public string $userName,
        public array $detailRows,
        public array $productTotals,
        public array $currencyTotals,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your commission summary — {$this->monthLabel} — {$this->companyName}",
        );
    }

    /** File-safe token for PDF naming (calendar month or date span). */
    public function attachmentPeriodToken(): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $this->yearMonth)) {
            return str_replace('-', '_', $this->yearMonth);
        }

        return preg_replace('/[^a-zA-Z0-9_-]+/', '_', $this->yearMonth) ?: 'period';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.commission_monthly_user',
            with: [
                'companyName' => $this->companyName,
                'monthLabel' => $this->monthLabel,
                'userName' => $this->userName,
                'detailRows' => $this->detailRows,
                'productTotals' => $this->productTotals,
                'currencyTotals' => $this->currencyTotals,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $stamp = Carbon::now()->toDayDateTimeString();
        $safe = Str::slug($this->userName) ?: 'user';
        $period = $this->attachmentPeriodToken();
        $fileName = "commission_summary_{$safe}_{$period}.pdf";

        $brand = PdfDocumentBranding::package();

        $pdf = Pdf::loadView('commission.pdf_monthly_user', [
            'companyName' => $this->companyName,
            'monthLabel' => $this->monthLabel,
            'userName' => $this->userName,
            'productTotals' => $this->productTotals,
            'detailRows' => $this->detailRows,
            'currencyTotals' => $this->currencyTotals,
            'generatedAt' => $stamp,
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
            Attachment::fromData(fn () => $pdf->output(), $fileName)->withMime('application/pdf'),
        ];
    }
}
