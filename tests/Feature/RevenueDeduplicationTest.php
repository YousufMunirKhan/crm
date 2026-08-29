<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Executive revenue used to be `won lead_items + invoices`, with no key to
 * de-duplicate them - so a deal that was both marked won and invoiced was
 * counted twice, and the headline number could not be trusted.
 */
class RevenueDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeadWithWonItem(Customer $customer, float $value): Lead
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'won',
            'source' => 'website',
            'assigned_to' => $user->id,
            'pipeline_value' => $value,
        ]);

        $product = Product::create(['name' => 'Widget', 'is_active' => true]);

        LeadItem::create([
            'lead_id' => $lead->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $value,
            'total_price' => $value,
            'status' => LeadItem::STATUS_WON,
        ]);

        return $lead;
    }

    private function makeInvoice(Customer $customer, float $total, ?Lead $lead = null): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV/'.date('Y').'/'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'lead_id' => $lead?->id,
            'invoice_date' => now(),
            'subtotal' => $total,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'sent',
        ]);
    }

    private function revenue(): float
    {
        return (float) app(ReportingService::class)->getExecutiveDashboard([
            'from' => now()->subYear()->toDateString(),
            'to' => now()->addYear()->toDateString(),
        ])['revenue'];
    }

    public function test_an_invoiced_won_lead_is_counted_once_not_twice(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'phone' => '447700900001']);
        $lead = $this->makeLeadWithWonItem($customer, 500.00);
        $this->makeInvoice($customer, 500.00, $lead);

        // One sale, invoiced. Previously this returned 1000.00.
        $this->assertSame(500.00, $this->revenue());
    }

    public function test_a_won_lead_with_no_invoice_still_counts(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'phone' => '447700900002']);
        $this->makeLeadWithWonItem($customer, 500.00);

        $this->assertSame(500.00, $this->revenue());
    }

    public function test_an_invoice_with_no_lead_still_counts(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'phone' => '447700900003']);
        $this->makeInvoice($customer, 750.00);

        $this->assertSame(750.00, $this->revenue());
    }

    public function test_unrelated_leads_and_invoices_are_both_counted(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'phone' => '447700900004']);

        // A won deal that was never invoiced, plus a separate ad-hoc invoice.
        $this->makeLeadWithWonItem($customer, 200.00);
        $this->makeInvoice($customer, 300.00);

        $this->assertSame(500.00, $this->revenue());
    }
}
