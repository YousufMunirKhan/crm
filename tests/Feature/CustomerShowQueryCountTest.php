<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\Invoice\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * The customer detail page is the highest-traffic screen in the app. It eager
 * loads its relations and then used to re-open five of them with ()->, paying
 * for the same data twice.
 */
class CustomerShowQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_detail_does_not_requery_eager_loaded_relations(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $customer = Customer::create(['name' => 'Acme Ltd', 'phone' => '447700900800']);

        // Several leads and invoices so an N+1 would show up clearly.
        foreach (range(1, 5) as $i) {
            Lead::create(['customer_id' => $customer->id, 'stage' => 'lead']);
            app(InvoiceService::class)->create([
                'customer_id' => $customer->id,
                'items' => [['description' => 'Item '.$i, 'quantity' => 1, 'unit_price' => 100]],
            ]);
        }

        Sanctum::actingAs($user);

        DB::enableQueryLog();
        $this->getJson("/api/customers/{$customer->id}")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Eager loading is a fixed number of queries regardless of row counts.
        // The ceiling is deliberately generous; the point is that it does not
        // scale with the number of leads or invoices.
        $this->assertLessThan(
            40,
            $queries,
            "Customer detail issued {$queries} queries; relations are probably being re-queried."
        );
    }
}
