<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\HR\Models\EmployeeTarget;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every automated email can send itself to one address as an example, and each
 * marks that copy "[Preview]" so it cannot be mistaken in an inbox for the real
 * thing. The risk runs the other way too: a prefix written in the wrong branch
 * would go out to customers, and nobody would notice until one replied asking
 * what a preview is.
 */
class PreviewMarkingTest extends TestCase
{
    use RefreshDatabase;

    /** Enough of everything that every command has something to send. */
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $tz = config('app.display_timezone');

        $sales = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);
        $admin = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);

        $rep = User::factory()->create(['role_id' => $sales->id, 'is_active' => true, 'email' => 'rep@example.com']);
        User::factory()->create(['role_id' => $admin->id, 'is_active' => true, 'email' => 'boss@example.com']);

        EmployeeTarget::create([
            'user_id' => $rep->id,
            'month' => now($tz)->format('Y-m'),
            'target_appointments' => 10,
            'target_sales' => 5,
            'target_daily_leads' => 5,
        ]);

        $customer = Customer::create([
            'name' => 'Kaleem',
            'business_name' => 'Corner Shop',
            'phone' => '07700900123',
            'email' => 'kaleem@example.com',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $rep->id,
            'next_follow_up_at' => now($tz)->subDay()->setTime(9, 0),
        ]);

        // One a day out for the reminder, one today for the morning list.
        foreach ([now($tz)->addDay(), now($tz)] as $at) {
            LeadActivity::create([
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

        foreach ([now($tz)->addDays(3), now($tz)->subDays(4)] as $dueDate) {
            Invoice::create([
                'invoice_number' => 'INV-'.random_int(10000, 99999),
                'customer_id' => $customer->id,
                'invoice_date' => now()->subDays(30)->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => 540,
                'vat_rate' => 0,
                'vat_amount' => 0,
                'total' => 540,
                'amount_paid' => 0,
                'currency' => 'GBP',
                'status' => 'sent',
            ]);
        }
    }

    /** @return string[] */
    private function commands(): array
    {
        return [
            'emails:appointment-reminders',
            'emails:rep-day',
            'emails:follow-up-digest',
            'emails:invoice-due',
            'emails:invoice-overdue',
            'emails:daily-lead-summary',
            'emails:weekly-team-summary',
        ];
    }

    public function test_a_real_send_never_says_preview(): void
    {
        foreach ($this->commands() as $command) {
            $this->artisan($command)->assertSuccessful();
        }

        $subjects = Mail::sent(AutomatedEmail::class)->map->mailSubject;

        $this->assertNotEmpty($subjects, 'nothing was sent, so this proved nothing');

        foreach ($subjects as $subject) {
            $this->assertStringNotContainsString('[Preview]', $subject);
        }
    }

    public function test_every_example_says_preview(): void
    {
        foreach ($this->commands() as $command) {
            $this->artisan($command, ['--preview' => 'somebody@example.com'])->assertSuccessful();
        }

        $subjects = Mail::sent(AutomatedEmail::class)->map->mailSubject;

        $this->assertNotEmpty($subjects, 'no example was sent, so this proved nothing');

        foreach ($subjects as $subject) {
            $this->assertStringContainsString('[Preview]', $subject);
        }
    }

    public function test_an_example_only_goes_to_the_address_that_asked_for_it(): void
    {
        foreach ($this->commands() as $command) {
            $this->artisan($command, ['--preview' => 'somebody@example.com'])->assertSuccessful();
        }

        // A preview must not reach a customer or the team while somebody is
        // looking at how the template turned out.
        Mail::assertNotSent(AutomatedEmail::class, fn (AutomatedEmail $m) => ! $m->hasTo('somebody@example.com'));
    }
}
