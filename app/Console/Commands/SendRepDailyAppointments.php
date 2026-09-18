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
    protected $signature = 'emails:rep-day
        {--dry-run : List what would be sent without sending}
        {--preview= : Send one example to this address, built from real appointments}';

    protected $description = 'Email each person their appointments for today';

    public function handle(AutomatedEmailSender $sender): int
    {
        $tz = config('app.display_timezone');
        $today = now($tz)->toDateString();

        $appointments = LeadActivity::query()
            ->where('type', 'appointment')
            ->where('appointment_status', LeadActivity::APPOINTMENT_STATUS_PENDING)
            ->whereDate('appointment_date', $today)
            ->with(['lead.customer', 'lead.assignee', 'lead.items.product', 'lead.product', 'assignee', 'user'])
            ->get();

        $byRep = $appointments->groupBy(
            fn (LeadActivity $a) => $a->assigned_user_id
                ?? $a->lead?->assigned_to
                ?? $a->user_id
        );

        if ($preview = $this->option('preview')) {
            return $this->preview($sender, $preview, $tz);
        }

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
                ->map(fn (LeadActivity $a) => $this->row($a))
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

    /** One example, from whatever appointments actually exist. */
    private function preview(AutomatedEmailSender $sender, string $to, string $tz): int
    {
        $appointments = LeadActivity::query()
            ->where('type', 'appointment')
            ->whereHas('lead.customer')
            ->with(['lead.customer', 'lead.items.product', 'lead.product', 'assignee', 'user'])
            ->latest('id')
            ->limit(3)
            ->get();

        if ($appointments->isEmpty()) {
            $this->warn('No appointment to build an example from.');

            return self::SUCCESS;
        }

        $rows = $appointments->map(fn (LeadActivity $a) => $this->row($a))->all();

        $mail = new AutomatedEmail(
            mailSubject: '[Preview] '.count($rows).' '.(count($rows) === 1 ? 'appointment' : 'appointments').' today',
            mailView: 'emails.automated.rep-day',
            mailData: [
                'repName' => $appointments->first()->assignee?->name ?: 'there',
                'today' => now($tz)->format('l j F'),
                'appointments' => $rows,
            ],
        );

        $sender->send('preview:rep-day:'.now()->format('YmdHis'), $to, $mail);
        $this->info("Sent a daily appointment list example to {$to}.");

        return self::SUCCESS;
    }

    /**
     * The same detail the booking email already gives them.
     *
     * That one carries the products, the value and a way back into the record;
     * this one had the name and a time, so the morning list was thinner than
     * the message they got when it was booked.
     *
     * @return array<string, mixed>
     */
    private function row(LeadActivity $a): array
    {
        $customer = $a->lead?->customer;
        $products = $a->lead?->items
            ?->map(fn ($item) => $item->product?->name)
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        if ($products === [] && $a->lead?->product?->name) {
            $products[] = $a->lead->product->name;
        }

        return [
            'time' => $a->appointment_time ?: '10:00',
            'customer' => $customer?->business_name ?: ($customer?->name ?: 'Customer'),
            'contact' => $customer?->business_name ? $customer?->name : null,
            'place' => $this->place($customer),
            'phone' => $customer?->phone,
            'email' => $customer?->email,
            'about' => $a->description,
            'products' => $products,
            'lead_id' => $a->lead_id,
            'stage' => $a->lead?->stage ? ucwords(str_replace('_', ' ', $a->lead->stage)) : null,
            'value' => (float) ($a->lead?->pipeline_value ?? 0),
            'url' => $a->lead_id ? rtrim((string) config('app.url'), '/').'/appointments/'.$a->id : null,
        ];
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
