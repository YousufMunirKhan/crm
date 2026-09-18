<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Models\User;
use App\Modules\CRM\Models\LeadActivity;
use App\Services\AutomatedEmailSender;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * What each person is due at today, before they start.
 *
 * Scheduled on the display timezone, not the application's: the app stores UTC
 * and always will, so a seven o'clock asked for out loud is eight o'clock for
 * the seven months of British Summer Time if this is hung off config('app.timezone').
 */
class SendRepDailyAppointments extends Command
{
    protected $signature = 'emails:rep-day {--dry-run : List what would be sent without sending}';

    protected $description = 'Email each person their appointments for today';

    public function handle(AutomatedEmailSender $sender): int
    {
        $tz = config('app.display_timezone');
        $today = now($tz)->toDateString();

        $appointments = LeadActivity::query()
            ->where('type', 'appointment')
            ->where('appointment_status', LeadActivity::APPOINTMENT_STATUS_PENDING)
            ->whereDate('appointment_date', $today)
            ->with(['lead.customer', 'lead.assignee', 'assignee', 'user'])
            ->get();

        $byRep = $appointments->groupBy(
            fn (LeadActivity $a) => $a->assigned_user_id
                ?? $a->lead?->assigned_to
                ?? $a->user_id
        );

        $sent = 0;

        foreach ($byRep as $userId => $theirs) {
            if (! $userId) {
                continue;
            }

            $user = User::find($userId);
            $email = trim((string) ($user->email ?? ''));

            if (! $user || $email === '') {
                continue;
            }

            $rows = $theirs
                ->sortBy(fn (LeadActivity $a) => $a->appointment_time ?: '00:00')
                ->values()
                ->map(function (LeadActivity $a) {
                    $customer = $a->lead?->customer;

                    return [
                        'time' => $a->appointment_time ?: '10:00',
                        'customer' => $customer?->business_name ?: ($customer?->name ?: 'Customer'),
                        'place' => $this->place($customer),
                        'phone' => $customer?->phone,
                        'about' => $a->description,
                    ];
                })
                ->all();

            if ($this->option('dry-run')) {
                $this->line(sprintf('%s <%s>: %d appointment(s)', $user->name, $email, count($rows)));

                continue;
            }

            $mail = new AutomatedEmail(
                mailSubject: count($rows).' '.(count($rows) === 1 ? 'appointment' : 'appointments').' today',
                mailView: 'emails.automated.rep-day',
                mailData: [
                    'companyName' => $sender->companyName(),
                    'repName' => $user->name,
                    'today' => Carbon::parse($today, $tz)->format('l j F'),
                    'appointments' => $rows,
                ],
            );

            if ($sender->send('rep-day:'.$user->id.':'.$today, $email, $mail)) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? $byRep->count().' person/people have appointments today.'
            : "Sent {$sent} daily appointment list(s).");

        return self::SUCCESS;
    }

    private function place($customer): ?string
    {
        if (! $customer) {
            return null;
        }

        $parts = array_filter([
            $customer->address,
            $customer->city,
            $customer->postcode,
        ], fn ($p) => trim((string) $p) !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
