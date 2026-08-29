<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There was no lead -> invoice conversion anywhere: a won deal had to be
 * re-keyed by hand, and lead_items / invoice_items were two disconnected
 * line-item systems with no shared product id.
 */
class LeadToInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function wonLead(float $unitPrice = 250.00, int $qty = 2): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $customer = Customer::create(['name' => 'Acme Ltd', 'phone' => '447700900500']);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'won',
            'assigned_to' => $user->id,
            'pipeline_value' => $unitPrice * $qty,
        ]);

        $product = Product::create([
            'name' => 'Retail ePOS',
            'unit_price' => $unitPrice,
            'cost_price' => 100.00,
            'is_active' => true,
        ]);

        LeadItem::create([
            'lead_id' => $lead->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $qty,
            'status' => LeadItem::STATUS_WON,
        ]);

        return [$lead, $product, $user];
    }

    public function test_converting_a_won_lead_carries_line_items_and_product_links(): void
    {
        [$lead, $product, $user] = $this->wonLead();

        $invoice = app(InvoiceService::class)->createFromLead($lead->fresh(), [], $user->id);

        $this->assertSame($lead->id, $invoice->lead_id);
        $this->assertCount(1, $invoice->items);

        $item = $invoice->items->first();
        $this->assertSame($product->id, $item->product_id, 'Invoice line must link to the catalogue product');
        $this->assertSame('Retail ePOS', $item->description);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(500.00, (float) $item->line_total);
    }

    public function test_a_lead_cannot_be_invoiced_twice(): void
    {
        [$lead, , $user] = $this->wonLead();
        $service = app(InvoiceService::class);

        $service->createFromLead($lead->fresh(), [], $user->id);

        $this->expectException(\RuntimeException::class);
        $service->createFromLead($lead->fresh(), [], $user->id);
    }

    public function test_a_lead_with_no_won_items_cannot_be_invoiced(): void
    {
        $customer = Customer::create(['name' => 'Acme', 'phone' => '447700900501']);
        $lead = Lead::create(['customer_id' => $customer->id, 'stage' => 'quotation']);

        $this->expectException(\RuntimeException::class);
        app(InvoiceService::class)->createFromLead($lead, [], null);
    }

    public function test_converted_invoice_does_not_double_count_revenue(): void
    {
        [$lead, , $user] = $this->wonLead();
        app(InvoiceService::class)->createFromLead($lead->fresh(), [], $user->id);

        $revenue = (float) app(\App\Modules\Reporting\Services\ReportingService::class)
            ->getExecutiveDashboard([
                'from' => now()->subYear()->toDateString(),
                'to' => now()->addYear()->toDateString(),
            ])['revenue'];

        // 500 net + 20% VAT on the invoice; the won lead items must not be
        // added on top of it.
        $invoiceTotal = (float) Invoice::where('lead_id', $lead->id)->value('total');
        $this->assertSame($invoiceTotal, $revenue);
    }

    public function test_product_performance_credits_the_converted_invoice(): void
    {
        [$lead, $product, $user] = $this->wonLead();
        app(InvoiceService::class)->createFromLead($lead->fresh(), [], $user->id);

        $report = app(\App\Modules\Reporting\Services\ReportingService::class)
            ->getProductPerformance([
                'from' => now()->subYear()->toDateString(),
                'to' => now()->addYear()->toDateString(),
            ]);

        $row = collect($report['products'])->firstWhere('product_id', $product->id);

        $this->assertNotNull($row, 'Converted invoice revenue must be attributable to the product');
        $this->assertSame(2, $row['units']);
        $this->assertSame(500.00, $row['revenue']);
        // cost 100 x 2 units against 500 revenue
        $this->assertSame(300.00, $row['margin']);
    }
}
