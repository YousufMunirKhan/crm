<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Search used to mean something different on every list. Customers matched six
 * columns, invoices matched the contact name only, and leads and tickets had no
 * search at all - so "Bright Star Ltd" found the customer, missed their invoice
 * and could not find their lead. All four now run through
 * Customer::scopeSearch().
 */
class ListingSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $target;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);

        $this->target = Customer::create([
            'name' => 'Priya Sharma',
            'business_name' => 'Bright Star Catering Ltd',
            'owner_name' => 'Devendra Sharma',
            'phone' => '+44 7700 900123',
            'email' => 'priya@brightstar.co.uk',
            'vat_number' => 'GB123456789',
            'postcode' => 'M1 4WU',
        ]);

        Customer::create([
            'name' => 'Someone Else',
            'business_name' => 'Unrelated Trading',
            'phone' => '447700111222',
        ]);
    }

    private function customerIds(string $term): array
    {
        return $this->actingAs($this->admin)
            ->getJson('/api/customers?search='.urlencode($term))
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_customers_are_found_by_company_name(): void
    {
        $this->assertSame([$this->target->id], $this->customerIds('Bright Star'));
    }

    public function test_customers_are_found_by_contact_name(): void
    {
        $this->assertSame([$this->target->id], $this->customerIds('Priya'));
    }

    public function test_customers_are_found_by_owner_name(): void
    {
        $this->assertSame([$this->target->id], $this->customerIds('Devendra'));
    }

    public function test_customers_are_found_by_vat_number(): void
    {
        $this->assertSame([$this->target->id], $this->customerIds('GB123456789'));
    }

    /**
     * The same mobile is stored as "+44 7700 900123" here and "07700900123" on
     * an imported row, so a literal LIKE finds neither from the other.
     */
    public function test_a_phone_number_matches_across_formats(): void
    {
        $this->assertSame([$this->target->id], $this->customerIds('07700900123'));
        $this->assertSame([$this->target->id], $this->customerIds('7700 900123'));
        $this->assertSame([$this->target->id], $this->customerIds('+447700900123'));
    }

    public function test_a_search_that_matches_nothing_returns_nothing(): void
    {
        $this->assertSame([], $this->customerIds('Zzzz No Such Company'));
    }

    public function test_leads_are_found_by_the_company_name_of_their_customer(): void
    {
        $lead = Lead::create([
            'customer_id' => $this->target->id,
            'stage' => 'lead',
            'source' => 'website',
            'created_by' => $this->admin->id,
        ]);
        Lead::create([
            'customer_id' => Customer::where('name', 'Someone Else')->value('id'),
            'stage' => 'lead',
            'created_by' => $this->admin->id,
        ]);

        $ids = $this->actingAs($this->admin)
            ->getJson('/api/leads?search='.urlencode('Bright Star'))
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$lead->id], $ids);
    }

    public function test_a_lead_can_be_found_by_its_number(): void
    {
        $lead = Lead::create([
            'customer_id' => $this->target->id,
            'stage' => 'lead',
            'created_by' => $this->admin->id,
        ]);

        $ids = $this->actingAs($this->admin)
            ->getJson('/api/leads?search=%23'.$lead->id)
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$lead->id], $ids);
    }

    public function test_invoices_are_found_by_company_name(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/00001',
            'customer_id' => $this->target->id,
            'invoice_date' => now(),
            'subtotal' => 100,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'total' => 100,
            'status' => 'unpaid',
            'created_by' => $this->admin->id,
        ]);

        $ids = $this->actingAs($this->admin)
            ->getJson('/api/invoices?search='.urlencode('Bright Star'))
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$invoice->id], $ids);
    }

    public function test_tickets_are_found_by_subject_and_by_company_name(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-0001',
            'source' => 'crm',
            'customer_id' => $this->target->id,
            'created_by' => $this->admin->id,
            'subject' => 'Card reader keeps rebooting',
            'description' => 'Happens mid-transaction.',
            'priority' => 'high',
            'status' => 'open',
        ]);

        foreach (['rebooting', 'Bright Star', 'Priya'] as $term) {
            $ids = $this->actingAs($this->admin)
                ->getJson('/api/tickets?search='.urlencode($term))
                ->assertOk()
                ->json('data.*.id');

            $this->assertSame([$ticket->id], $ids, "search term: {$term}");
        }
    }

    /**
     * A wildcard typed into the box is a literal, not an operator - otherwise a
     * stray "%" quietly returns the whole table.
     */
    public function test_a_percent_sign_is_treated_as_a_literal(): void
    {
        $this->assertSame([], $this->customerIds('%'));
    }
}
