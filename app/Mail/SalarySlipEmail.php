<?php

namespace App\Mail;

use App\Modules\HR\Models\Salary;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalarySlipEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Salary $salary,
        public string $month,
        public string $companyName,
        public string $logoUrl = '',
        public ?string $logoPath = null,
        public string $employeeName = '',
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
            subject: "Your Salary Slip - {$this->month} - {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.salary_slip',
            with: [
                'salary' => $this->salary,
                'month' => $this->month,
                'companyName' => $this->companyName,
                'logoUrl' => $this->logoUrl,
                'logoPath' => $this->logoPath,
                'employeeName' => $this->employeeName,
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
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('salaries.slip', ['salary' => $this->salary])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10)
            ->setOption('enable-local-file-access', true)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $fileName = "salary_slip_{$this->salary->user->name}_{$this->salary->month}.pdf";

        return [
            Attachment::fromData(fn () => $pdf->output(), $fileName)
                ->withMime('application/pdf'),
        ];
    }
}
