<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Settings\Models\Setting;

class AppointmentAssignedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public LeadActivity $activity;

    public function __construct(LeadActivity $activity)
    {
        $this->activity = $activity;
    }

    public function envelope(): Envelope
    {
        $companyName = Setting::where('key', 'company_name')->first()?->value ?? 'CRM';
        return new Envelope(
            subject: "Appointment booked – You are scheduled – {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.appointment-assigned');
    }

    public function build()
    {
        $companyName = Setting::where('key', 'company_name')->first()?->value ?? 'CRM';
        $logoUrl = Setting::where('key', 'logo_url')->first()?->value ?? '';
        if ($logoUrl && !str_starts_with($logoUrl, 'http')) {
            $logoUrl = config('app.url') . $logoUrl;
        }
        $activity = $this->activity->load(['lead.customer', 'assignee', 'user']);
        $meta = is_array($activity->meta) ? $activity->meta : [];
        $appointmentDate = isset($meta['appointment_date'])
            ? \Carbon\Carbon::parse($meta['appointment_date'])->format('l, d F Y')
            : '—';
        $rawTime = $meta['appointment_time'] ?? '10:00';
        $appointmentTime = \Carbon\Carbon::parse('2000-01-01 ' . $rawTime)->format('g:i A');
        $notes = $activity->description ?? '';

        return $this->subject("Appointment booked – Please attend at {$appointmentTime} on {$appointmentDate} – {$companyName}")
            ->view('emails.appointment-assigned')
            ->with([
                'activity' => $activity,
                'lead' => $activity->lead,
                'customer' => $activity->lead->customer,
                'appointmentDate' => $appointmentDate,
                'appointmentTime' => $appointmentTime,
                'notes' => $notes,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'appointmentUrl' => config('app.url') . '/appointments/' . $activity->id,
            ]);
    }
}
