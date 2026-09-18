<?php

namespace Tests\Feature;

use App\Mail\AutomatedEmail;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A weekly chase with no way to be told it is wrong.
 *
 * Somebody who paid by transfer before anyone reconciled it gets chased again
 * every seven days until a person happens to notice, which is how a reminder
 * turns into an annoyance and then into a complaint. The link in the email is
 * the customer's way of stopping it - and it records a claim rather than a
 * payment, because marking an invoice settled on somebody's say-so would put a
 * hole in the accounts.
 */
class InvoicePaymentClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function invoice(array $attributes = []): Invoice
    {
        $customer = Customer::create([
            'name' => 'Kaleem',
            'business_name' => 'Corner Shop',
            'phone' => '07700900123',
            'email' => 'kaleem@example.com',
        ]);

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-'.random_int(10000, 99999),
            'customer_id' => $customer->id,
            'invoice_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
            'subtotal' => 540,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'total' => 540,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'overdue',
        ], $attributes));
    }

    private function link(Invoice $invoice): string
    {
        return URL::signedRoute('invoices.payment-claimed', ['invoice' => $invoice->id]);
    }

    public function test_the_chase_carries_a_way_to_stop_it(): void
    {
        $this->invoice();

        $this->artisan('emails:invoice-overdue')->assertSuccessful();

        Mail::assertSent(AutomatedEmail::class, fn (AutomatedEmail $m) => ! empty($m->mailData['paidUrl']));
    }

    public function test_saying_it_is_paid_stops_the_chasing(): void
    {
        $invoice = $this->invoice();

        $this->get($this->link($invoice))->assertOk()->assertSee('stop sending you reminders', false);

        $this->assertNotNull($invoice->refresh()->payment_claimed_at);

        Mail::fake();
        $this->artisan('emails:invoice-overdue')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_it_also_stops_the_due_soon_reminder(): void
    {
        $invoice = $this->invoice([
            'due_date' => now(config('app.display_timezone'))->addDays(3)->toDateString(),
            'status' => 'sent',
        ]);

        $this->get($this->link($invoice))->assertOk();

        Mail::fake();
        $this->artisan('emails:invoice-due')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_it_does_not_mark_the_invoice_paid(): void
    {
        $invoice = $this->invoice();

        $this->get($this->link($invoice))->assertOk();
        $invoice->refresh();

        // A claim, not a payment: the ledger is untouched.
        $this->assertSame('overdue', $invoice->status);
        $this->assertSame('0.00', (string) $invoice->amount_paid);
    }

    public function test_somebody_is_told_to_check_it(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $invoice = $this->invoice();

        $this->get($this->link($invoice))->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => 'invoice_payment_claimed',
        ]);
    }

    public function test_clicking_twice_does_not_tell_everybody_twice(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);
        User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $invoice = $this->invoice();

        $this->get($this->link($invoice))->assertOk();
        $this->get($this->link($invoice))->assertOk();

        $this->assertSame(1, Notification::where('type', 'invoice_payment_claimed')->count());
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $invoice = $this->invoice();

        $this->get("/invoices/{$invoice->id}/payment-claimed")->assertForbidden();

        $this->assertNull($invoice->refresh()->payment_claimed_at);
    }

    public function test_somebody_elses_invoice_cannot_be_reached_by_editing_the_number(): void
    {
        $mine = $this->invoice();
        $theirs = $this->invoice();

        // The signature covers the id, so swapping it invalidates the link.
        $tampered = str_replace('/invoices/'.$mine->id.'/', '/invoices/'.$theirs->id.'/', $this->link($mine));

        $this->get($tampered)->assertForbidden();

        $this->assertNull($theirs->refresh()->payment_claimed_at);
    }
}
