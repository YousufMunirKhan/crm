<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Modules\CRM\Models\LeadActivity;
use App\Services\AutomatedEmailSender;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Tells a customer the day before that we are coming.
 *
 * Runs hourly and takes the hour that is a day out, so "24 hours before" means
 * roughly that whatever time the appointment is at, rather than everything
 * landing in one morning batch. The send is recorded against the appointment,
 * so the hour either side of the boundary cannot send it twice.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'emails:appointment-reminders
        {--dry-run : List what would be sent without sending}
        {--preview= : Send one example to this address, built from a real appointment}';

    protected $description = 'Email customers a reminder about tomorrow\'s appointment';

    public function handle(AutomatedEmailSender $sender): int
    {
        $tz = config('app.display_timezone');
        $from = now($tz)->addDay()->startOfHour();
        $until = $from->copy()->addHour();

        $appointments = LeadActivity::query()
            ->where('type', 'appointment')
            ->where('appointment_status', LeadActivity::APPOINTMENT_STATUS_PENDING)
            ->whereNotNull('appointment_date')
            ->whereDate('appointment_date', '>=', $from->toDateString())
            ->whereDate('appointment_date', '<=', $until->toDateString())
            ->with(['lead.customer', 'lead.assignee', 'assignee', 'user'])
            ->get()
            ->filter(function (LeadActivity $a) use ($tz, $from, $until) {
                $at = $this->appointmentAt($a, $tz);

                return $at !== null && $at->gte($from) && $at->lt($until);
            });

        if ($preview = $this->option('preview')) {
            return $this->preview($sender, $preview, $tz);
        }

        $sent = 0;

        foreach ($appointments as $appointment) {
            $customer = $appointment->lead?->customer;
            $email = trim((string) ($customer->email ?? ''));

            if ($email === '') {
                continue;
            }

            $at = $this->appointmentAt($appointment, $tz);
            $rep = $appointment->assignee ?? $appointment->lead?->assignee ?? $appointment->user;
            $reference = 'appointment-reminder:'.$appointment->id;

            if ($this->option('dry-run')) {
                $this->line(sprintf('%s  %s  <%s>', $at->format('d M H:i'), $customer->business_name ?: $customer->name, $email));

                continue;
            }

            $mail = new AutomatedEmail(
                mailSubject: 'Reminder: your appointment on '.$at->format('D j M').' at '.$at->format('H:i'),
                mailView: 'emails.automated.appointment-reminder',
                mailData: [
                    'companyName' => $sender->companyName(),
                    'customerName' => $customer->name ?: ($customer->business_name ?: 'there'),
                    'when' => $at->format('l j F Y').' at '.$at->format('H:i'),
                    'place' => $this->place($customer),
                    'repName' => $rep?->name,
                    'repPhone' => $rep?->phone ?? null,
                    'about' => $appointment->description,
                ],
            );

            if ($sender->send($reference, $email, $mail, ['customer_id' => $customer->id, 'lead_id' => $appointment->lead_id])) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? $appointments->count().' appointment(s) in the hour a day from now.'
            : "Sent {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }

    /**
     * One example of this email, built from a real appointment so the preview
     * shows the template as it will actually arrive rather than as lorem.
     */
    private function preview(AutomatedEmailSender $sender, string $to, string $tz): int
    {
        $appointment = LeadActivity::query()
            ->where('type', 'appointment')
            ->whereHas('lead.customer')
            ->with(['lead.customer', 'lead.assignee', 'assignee', 'user'])
            ->latest('id')
            ->first();

        if (! $appointment) {
            $this->warn('No appointment to build an example from.');

            return self::SUCCESS;
        }

        $customer = $appointment->lead->customer;
        $rep = $appointment->assignee ?? $appointment->lead?->assignee ?? $appointment->user;
        $at = now($tz)->addDay();

        $mail = new AutomatedEmail(
            mailSubject: '[Preview] Reminder: your appointment on '.$at->format('D j M').' at '.$at->format('H:i'),
            mailView: 'emails.automated.appointment-reminder',
            mailData: [
                'customerName' => $customer->name ?: ($customer->business_name ?: 'there'),
                'when' => $at->format('l j F Y').' at '.$at->format('H:i'),
                'place' => $this->place($customer),
                'repName' => $rep?->name,
                'repPhone' => $rep?->phone ?? null,
                'about' => $appointment->description,
            ],
        );

        $sender->send('preview:appointment-reminder:'.now()->format('YmdHis'), $to, $mail);
        $this->info("Sent an appointment reminder example to {$to}.");

        return self::SUCCESS;
    }

    /** The appointment as a moment in UK time, from its date and its "HH:MM". */
    private function appointmentAt(LeadActivity $a, string $tz): ?Carbon
    {
        if (! $a->appointment_date) {
            return null;
        }

        $time = trim((string) ($a->appointment_time ?: '10:00'));

        if (! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }

        return Carbon::parse($a->appointment_date->toDateString(), $tz)
            ->setTime((int) $m[1], (int) $m[2]);
    }

    private function place($customer): ?string
    {
        $parts = array_filter([
            $customer->address ?? null,
            $customer->city ?? null,
            $customer->postcode ?? null,
        ], fn ($p) => trim((string) $p) !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
