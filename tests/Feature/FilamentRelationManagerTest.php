<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\Product;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edit pages are where relation managers actually execute; a bad relationship
 * name or a pivot column that does not exist only surfaces on render.
 */
class FilamentRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Acme Ltd', 'phone' => '447700900700']);
    }

    public function test_product_edit_page_renders_with_the_cross_sell_manager(): void
    {
        $base = Product::create(['name' => 'Retail ePOS', 'is_active' => true]);
        $upsell = Product::create(['name' => 'Support Plan', 'is_active' => true]);

        $base->suggestedProducts()->attach($upsell->id, ['relationship_type' => 'upsell']);

        $this->actingAs($this->admin())
            ->get("/admin/products/{$base->id}/edit")
            ->assertSuccessful();
    }

    public function test_cross_sell_pivot_keeps_its_relationship_type(): void
    {
        // Previously hardcoded to 'suggest' at every read site, which lost the
        // upsell / cross-sell distinction even when rows existed.
        $base = Product::create(['name' => 'Base', 'is_active' => true]);
        $other = Product::create(['name' => 'Other', 'is_active' => true]);

        $base->suggestedProducts()->attach($other->id, ['relationship_type' => 'cross_sell']);

        $linked = $base->fresh('suggestedProducts')->suggestedProducts->first();

        $this->assertSame('cross_sell', $linked->pivot->relationship_type);
    }

    public function test_customer_edit_page_renders_with_leads_and_invoices(): void
    {
        $customer = $this->customer();
        Lead::create(['customer_id' => $customer->id, 'stage' => 'lead']);

        app(InvoiceService::class)->create([
            'customer_id' => $customer->id,
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/edit")
            ->assertSuccessful();
    }

    public function test_invoice_edit_page_renders_with_items_and_payments(): void
    {
        $customer = $this->customer();

        $invoice = app(InvoiceService::class)->create([
            'customer_id' => $customer->id,
            'items' => [['description' => 'Retail ePOS', 'quantity' => 2, 'unit_price' => 250]],
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/invoices/{$invoice->id}/edit")
            ->assertSuccessful();
    }

    public function test_lead_edit_page_renders_with_line_items(): void
    {
        $customer = $this->customer();
        $lead = Lead::create(['customer_id' => $customer->id, 'stage' => 'won']);

        $this->actingAs($this->admin())
            ->get("/admin/leads/{$lead->id}/edit")
            ->assertSuccessful();
    }

    public function test_ticket_edit_page_renders_with_messages(): void
    {
        $customer = $this->customer();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-'.uniqid(),
            'customer_id' => $customer->id,
            'subject' => 'Terminal offline',
            'description' => 'Card machine will not connect',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/tickets/{$ticket->id}/edit")
            ->assertSuccessful();
    }
}
