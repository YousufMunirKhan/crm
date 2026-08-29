<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "overdue" was a valid invoice status that nothing could ever set, so
 * accounts-receivable ageing was impossible to report on.
 */
class InvoiceOverdueTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(array $attributes = []): Invoice
    {
        $customer = Customer::create([
            'name' => 'Acme Ltd',
            'phone' => '4477009009'.random_int(10, 99),
        ]);

        return Invoice::create(array_merge([
            'invoice_number' => 'INV/'.date('Y').'/'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'invoice_date' => now()->subDays(60),
            'due_date' => now()->subDays(30),
            'subtotal' => 100,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'total' => 100,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'sent',
        ], $attributes));
    }

    public function test_an_unpaid_invoice_past_its_due_date_becomes_overdue(): void
    {
        $invoice = $this->invoice();

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_a_partially_paid_invoice_past_due_becomes_overdue(): void
    {
        $invoice = $this->invoice(['status' => 'partially_paid', 'amount_paid' => 40]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_an_invoice_not_yet_due_is_left_alone(): void
    {
        $invoice = $this->invoice(['due_date' => now()->addDays(14)]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_a_fully_paid_invoice_is_never_marked_overdue(): void
    {
        $invoice = $this->invoice(['amount_paid' => 100, 'status' => 'paid']);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_a_draft_invoice_is_left_alone(): void
    {
        // A draft has not been sent to anyone, so it cannot be overdue.
        $invoice = $this->invoice(['status' => 'draft']);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $invoice = $this->invoice();

        $this->artisan('invoices:mark-overdue', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('sent', $invoice->fresh()->status);
    }
}
