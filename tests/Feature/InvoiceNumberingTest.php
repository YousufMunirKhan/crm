<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoice numbers are unique across the whole invoices table, deleted rows
 * included, because deleting an invoice only soft deletes it. Numbering that
 * reads live rows only reissues a number that is still held, and the create
 * dies on the unique index - which is what "something went wrong" was on the
 * new invoice screen for anybody who had ever deleted one.
 */
class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function payload(int $customerId): array
    {
        return [
            'invoice_date' => '2026-09-02',
            'due_date' => '2026-10-02',
            'vat_rate' => 20,
            'status' => 'draft',
            'customer_id' => $customerId,
            'items' => [
                ['description' => 'Retail ePOS', 'quantity' => 1, 'unit_price' => 250],
            ],
        ];
    }

    public function test_an_invoice_can_be_raised_after_deleting_the_previous_one(): void
    {
        $user = $this->admin();
        $customer = Customer::create(['name' => 'Acme Ltd', 'phone' => '447700900500']);

        $first = $this->actingAs($user)->postJson('/api/invoices', $this->payload($customer->id));
        $first->assertStatus(201);

        $this->actingAs($user)
            ->deleteJson('/api/invoices/'.$first->json('id'))
            ->assertNoContent();

        $second = $this->actingAs($user)->postJson('/api/invoices', $this->payload($customer->id));

        $second->assertStatus(201);
        $this->assertNotSame($first->json('invoice_number'), $second->json('invoice_number'));
    }

    public function test_numbering_steps_over_a_number_it_cannot_parse(): void
    {
        $customer = Customer::create(['name' => 'Acme Ltd', 'phone' => '447700900501']);
        $year = date('Y');

        // The shape an import or an older numbering scheme leaves behind: it
        // matches the year prefix but not the suffix this code writes.
        Invoice::create([
            'invoice_number' => 'INV/'.$year.'/00001-R',
            'customer_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100,
            'vat_rate' => 20,
            'vat_amount' => 20,
            'total' => 120,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'draft',
        ]);
        Invoice::create([
            'invoice_number' => 'INV/'.$year.'/00001',
            'customer_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100,
            'vat_rate' => 20,
            'vat_amount' => 20,
            'total' => 120,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => 'draft',
        ])->delete();

        $number = app(InvoiceService::class)->generateInvoiceNumber();

        $this->assertSame('INV/'.$year.'/00002', $number);
        $this->assertFalse(Invoice::withTrashed()->where('invoice_number', $number)->exists());
    }
}
