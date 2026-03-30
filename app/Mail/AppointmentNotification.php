<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Modules\CRM\Models\Lead;
use App\Modules\Settings\Models\Setting;

class AppointmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Lead $lead;
    public string $appointmentDate;
    public string $appointmentTime;
    public string $notes;
    public string $recipientType; // 'customer' or 'admin'

    /**
     * Create a new message instance.
     */
    public function __construct(Lead $lead, string $appointmentDate, string $appointmentTime, string $notes, string $recipientType = 'customer')
    {
        $this->lead = $lead;
        $this->appointmentDate = $appointmentDate;
        $this->appointmentTime = $appointmentTime;
        $this->notes = $notes;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = Setting::where('key', 'company_name')->first()?->value ?? 'Switch & Save';
        
        return new Envelope(
            subject: "Appointment Confirmation - {$companyName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $companyName = Setting::where('key', 'company_name')->first()?->value ?? 'Switch & Save';
        $companyPhone = Setting::where('key', 'company_phone')->first()?->value ?? '';
        $companyAddress = Setting::where('key', 'company_address')->first()?->value ?? '';
        $companyRegistrationNo = Setting::where('key', 'company_registration_no')->first()?->value ?? '';
        $companyVat = Setting::where('key', 'company_vat')->first()?->value ?? '';
        $companyWebsite = Setting::where('key', 'company_website')->first()?->value ?? '';
        $logoUrl = Setting::where('key', 'logo_url')->first()?->value ?? '';
        $socialFacebook = Setting::where('key', 'social_facebook_url')->first()?->value ?? '';
        $socialTwitter = Setting::where('key', 'social_twitter_url')->first()?->value ?? '';
        $socialLinkedIn = Setting::where('key', 'social_linkedin_url')->first()?->value ?? '';
        $socialInstagram = Setting::where('key', 'social_instagram_url')->first()?->value ?? '';
        $socialTikTok = Setting::where('key', 'social_tiktok_url')->first()?->value ?? '';

        if ($logoUrl && !str_starts_with($logoUrl, 'http')) {
            $logoUrl = config('app.url') . $logoUrl;
        }

        return $this->subject("Appointment Confirmation - {$companyName}")
            ->view('emails.appointment')
            ->with([
                'lead' => $this->lead,
                'customer' => $this->lead->customer,
                'appointmentDate' => $this->appointmentDate,
                'appointmentTime' => $this->appointmentTime,
                'notes' => $this->notes,
                'recipientType' => $this->recipientType,
                'companyName' => $companyName,
                'companyPhone' => $companyPhone,
                'companyAddress' => $companyAddress,
                'companyWebsite' => $companyWebsite,
                'companyRegistrationNo' => $companyRegistrationNo,
                'companyVat' => $companyVat,
                'logoUrl' => $logoUrl,
                'socialFacebook' => $socialFacebook,
                'socialTwitter' => $socialTwitter,
                'socialLinkedIn' => $socialLinkedIn,
                'socialInstagram' => $socialInstagram,
                'socialTikTok' => $socialTikTok,
            ]);
    }
}

