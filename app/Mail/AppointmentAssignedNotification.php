<?php

namespace App\Mail;

use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Settings\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentAssignedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public LeadActivity $activity;

    /**
     * Who is being written to, which is not always the appointment's assignee.
     *
     * An appointment can be booked without one, and somebody still has to go.
     */
    public ?\App\Models\User $recipient;

    public function __construct(LeadActivity $activity, ?\App\Models\User $recipient = null)
    {
        $this->activity = $activity;
        $this->recipient = $recipient;
    }

    public function envelope(): Envelope
    {
        $companyName = Setting::where('key', 'company_name')->first()?->value ?? 'CRM';

        return new Envelope(
            subject: "Appointment booked - You are scheduled - {$companyName}",
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
        if ($logoUrl && ! str_starts_with($logoUrl, 'http')) {
            $logoUrl = config('app.url').$logoUrl;
        }

        $activity = $this->activity->load([
            'lead.customer',
            'lead.assignee',
            'lead.creator',
            'lead.items.product',
            'lead.product',
            'assignee',
            'user',
        ]);

        $meta = is_array($activity->meta) ? $activity->meta : [];
        $rawDate = $activity->appointment_date?->toDateString() ?? ($meta['appointment_date'] ?? null);
        $appointmentDate = $rawDate
            ? \Carbon\Carbon::parse($rawDate)->format('l, d F Y')
            : '-';
        $rawTime = $activity->appointment_time ?? ($meta['appointment_time'] ?? '10:00');
        $appointmentTime = \Carbon\Carbon::parse('2000-01-01 '.$rawTime)->format('g:i A');
        $notes = $activity->description ?? '';
        $customer = $activity->lead?->customer;
        $productNames = $activity->lead?->items
            ? $activity->lead->items
                ->map(fn ($item) => $item->product?->name)
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];

        if ($productNames === [] && $activity->lead?->product?->name) {
            $productNames[] = $activity->lead->product->name;
        }

        $addressParts = array_filter([
            $customer?->address,
            $customer?->city,
            $customer?->postcode,
        ]);

        return $this->subject("Appointment booked - Please attend at {$appointmentTime} on {$appointmentDate} - {$companyName}")
            ->view('emails.appointment-assigned')
            ->with([
                'activity' => $activity,
                'lead' => $activity->lead,
                'customer' => $customer,
                'appointmentDate' => $appointmentDate,
                'appointmentTime' => $appointmentTime,
                'notes' => $notes,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'appointmentUrl' => config('app.url').'/appointments/'.$activity->id,
                'productNames' => $productNames,
                'businessName' => $customer?->business_name,
                'addressLine' => $addressParts ? implode(', ', $addressParts) : null,
                'leadStage' => $activity->lead?->stage,
                'leadSource' => $activity->lead?->source,
                'leadValue' => $activity->lead?->pipeline_value,
                'createdByName' => $activity->user?->name,
                'leadOwnerName' => $activity->lead?->assignee?->name,
                'recipientName' => $this->recipient?->name ?? $activity->assignee?->name,
                // Said out loud when it is going to somebody because nobody was
                // named, so they know why it landed with them.
                'isStandIn' => $this->recipient !== null
                    && $activity->assigned_user_id !== $this->recipient->id,
            ]);
    }
}
