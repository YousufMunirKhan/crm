<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\ContactConsent;
use App\Models\Role;
use App\Models\SentCommunication;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Invoice\Models\Invoice;
use App\Services\SuppressionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The reminders, chases and digests nobody sends by hand.
 *
 * All of them run again tomorrow, so what is worth pinning down is not that
 * they send - it is that they send once, at the hour a person in this country
 * would expect, and that an unsubscribe from marketing does not take somebody's
 * own invoice down with it.
 */
class AutomatedEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function rep(string $email = 'rep@example.com'): User
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'email' => $email]);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Aamir',
            'business_name' => 'Corner Shop',
            'phone' => '07700900'.random_int(100, 999),
            'email' => 'shop'.random_int(1, 99999).'@example.com',
            'address' => '12 High Street',
            'city' => 'Leicester',
            'postcode' => 'LE1 1AA',
        ], $attributes));
    }

    /**
     * An appointment at a fixed hour of some day, rather than "in N hours".
     *
     * Run late enough in the evening and "in two hours" is tomorrow, so a test
     * about today's list finds nothing and fails for the clock rather than the
     * code.
     */
    private function appointmentOn(string $date, string $time, ?User $rep = null): LeadActivity
    {
        return $this->appointmentAt(
            \Carbon\Carbon::parse($date.' '.$time, config('app.display_timezone')),
            $rep,
            null
        );
    }

    private function appointmentIn(int $hours, ?User $rep = null, ?Customer $customer = null): LeadActivity
    {
        return $this->appointmentAt(now(config('app.display_timezone'))->addHours($hours), $rep, $customer);
    }

    private function appointmentAt(\Carbon\Carbon $at, ?User $rep = null, ?Customer $customer = null): LeadActivity
    {
        $rep ??= $this->rep();
        $customer ??= $this->customer();

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $rep->id,
        ]);

        return LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $rep->id,
            'assigned_user_id' => $rep->id,
            'type' => 'appointment',
            'description' => 'EPOS demo',
            'appointment_date' => $at->toDateString(),
            'appointment_time' => $at->format('H:i'),
            'appointment_status' => LeadActivity::APPOINTMENT_STATUS_PENDING,
        ]);
    }

    private function invoice(array $attributes = []): Invoice
    {
        $customer = $attributes['customer'] ?? $this->customer();
        unset($attributes['customer']);

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-'.random_int(10000, 99999),
            'customer_id' => $customer->id,
            'invoice_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'subtotal' => 100,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'total' => 100,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'sent',
        ], $attributes));
    }

    // --------------------------------------------------- appointment reminder

    public function test_a_customer_is_reminded_the_day_before(): void
    {
        $appointment = $this->appointmentIn(24);

        $this->artisan('emails:appointment-reminders')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
        $this->assertDatabaseHas('sent_communications', [
            'reference' => 'appointment-reminder:'.$appointment->id,
            'status' => 'sent',
        ]);
    }

    public function test_the_next_hour_does_not_remind_them_again(): void
    {
        $this->appointmentIn(24);

        $this->artisan('emails:appointment-reminders')->assertSuccessful();
        $this->artisan('emails:appointment-reminders')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
    }

    public function test_an_appointment_further_out_waits_its_turn(): void
    {
        $this->appointmentIn(50);

        $this->artisan('emails:appointment-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_bounced_address_is_not_written_to_again(): void
    {
        $customer = $this->customer(['email' => 'dead@example.com']);
        app(SuppressionService::class)->optOut(
            'dead@example.com',
            ContactConsent::CHANNEL_EMAIL,
            'delivery_webhook_hard_bounce'
        );
        $this->appointmentIn(24, null, $customer);

        $this->artisan('emails:appointment-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------------- rep

    public function test_a_rep_gets_todays_appointments_once(): void
    {
        $rep = $this->rep('sales@example.com');
        $today = now(config('app.display_timezone'))->toDateString();
        $this->appointmentOn($today, '09:00', $rep);
        $this->appointmentOn($today, '14:00', $rep);

        $this->artisan('emails:rep-day')->assertSuccessful();
        $this->artisan('emails:rep-day')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => $m->hasTo('sales@example.com'));
        Mail::assertSent(AutomatedEmail::class, 1);
    }

    public function test_a_rep_with_nothing_on_is_not_emailed(): void
    {
        $this->rep();

        $this->artisan('emails:rep-day')->assertSuccessful();

        Mail::assertNothingSent();
    }

    // -------------------------------------------------------------- invoices

    public function test_an_invoice_is_chased_before_it_falls_due(): void
    {
        $invoice = $this->invoice(['due_date' => now(config('app.display_timezone'))->addDays(3)->toDateString()]);

        $this->artisan('emails:invoice-due')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
        $this->assertDatabaseHas('sent_communications', ['reference' => 'invoice-due:'.$invoice->id]);
    }

    public function test_a_paid_invoice_is_left_alone(): void
    {
        $this->invoice([
            'due_date' => now(config('app.display_timezone'))->addDays(3)->toDateString(),
            'amount_paid' => 100,
            'status' => 'paid',
        ]);

        $this->artisan('emails:invoice-due')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_an_overdue_invoice_is_chased_then_left_for_a_week(): void
    {
        $this->invoice(['due_date' => now()->subDays(2)->toDateString(), 'status' => 'overdue']);

        $this->artisan('emails:invoice-overdue')->assertSuccessful();
        Mail::assertSent(AutomatedEmail::class, 1);

        // The next morning, and a few after that: still inside the week.
        $this->travel(1)->days();
        $this->artisan('emails:invoice-overdue')->assertSuccessful();
        Mail::assertSent(AutomatedEmail::class, 1);

        $this->travel(3)->days();
        $this->artisan('emails:invoice-overdue')->assertSuccessful();
        Mail::assertSent(AutomatedEmail::class, 1);

        // A week on from the last one that went.
        $this->travel(4)->days();
        $this->artisan('emails:invoice-overdue')->assertSuccessful();
        Mail::assertSent(AutomatedEmail::class, 2);
    }

    public function test_unsubscribing_from_marketing_does_not_stop_your_own_invoice(): void
    {
        $customer = $this->customer(['email' => 'nomarketing@example.com']);
        app(SuppressionService::class)->optOut(
            'nomarketing@example.com',
            ContactConsent::CHANNEL_EMAIL,
            'unsubscribe_link'
        );
        $this->invoice([
            'customer' => $customer,
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'overdue',
        ]);

        $this->artisan('emails:invoice-overdue')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
    }

    // ------------------------------------------------------------ follow-ups

    public function test_the_digest_separates_overdue_from_due_today(): void
    {
        $rep = $this->rep('digest@example.com');
        $tz = config('app.display_timezone');

        // A fixed hour of today, not "in two hours": run this late enough in the
        // evening and the third one lands tomorrow, where the digest cannot see
        // it, and the test fails for the time of day rather than the code.
        $whens = [
            now($tz)->subDays(3)->setTime(9, 0),
            now($tz)->subDay()->setTime(9, 0),
            now($tz)->startOfDay()->setTime(9, 0),
        ];

        foreach ($whens as $when) {
            Lead::create([
                'customer_id' => $this->customer()->id,
                'stage' => 'follow_up',
                'assigned_to' => $rep->id,
                'next_follow_up_at' => $when,
            ]);
        }

        $this->artisan('emails:follow-up-digest')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, function (AutomatedEmail $m) {
            return count($m->mailData['overdue']) === 2
                && count($m->mailData['dueToday']) === 1;
        });
    }

    public function test_a_won_lead_is_not_chased(): void
    {
        $rep = $this->rep();
        Lead::create([
            'customer_id' => $this->customer()->id,
            'stage' => 'won',
            'assigned_to' => $rep->id,
            'next_follow_up_at' => now()->subDay(),
        ]);

        $this->artisan('emails:follow-up-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_digest_goes_once_a_day(): void
    {
        $rep = $this->rep();
        Lead::create([
            'customer_id' => $this->customer()->id,
            'stage' => 'follow_up',
            'assigned_to' => $rep->id,
            'next_follow_up_at' => now()->subDay(),
        ]);

        $this->artisan('emails:follow-up-digest')->assertSuccessful();
        $this->artisan('emails:follow-up-digest')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, 1);
    }

    // ------------------------------------------------------------- the clock

    /**
     * Seven in the morning means seven in the morning here.
     *
     * The application stores UTC and always will, so a time hung off
     * config('app.timezone') is an hour late for the seven months of British
     * Summer Time - which is exactly when somebody notices their day's list
     * arriving after they have already left.
     */
    public function test_the_morning_email_is_seven_oclock_uk_not_utc(): void
    {
        [$expression, $timezone] = $this->scheduleFor('emails:rep-day');

        $this->assertSame('0 7 * * *', $expression);
        $this->assertSame('Europe/London', $timezone);
    }

    public function test_every_named_time_is_on_the_uk_clock(): void
    {
        foreach (['emails:follow-up-digest', 'emails:invoice-due', 'emails:invoice-overdue'] as $command) {
            [, $timezone] = $this->scheduleFor($command);

            $this->assertSame('Europe/London', $timezone, $command.' is not on the UK clock');
        }
    }

    /** @return array{0: string, 1: string} the cron expression and timezone */
    private function scheduleFor(string $command): array
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, $command));

        $this->assertNotNull($event, $command.' is not scheduled at all');

        return [$event->expression, (string) $event->timezone];
    }
}
