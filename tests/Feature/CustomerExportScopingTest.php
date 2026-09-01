<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer CSV is every name, phone, email and address the company holds.
 *
 * `index()` has always decided who may see which customers; `export()` returned
 * `Customer::query()->get()` with no check of any kind, so any signed-in
 * account could download all 580 in one request - including accounts whose
 * sidebar does not show Customers at all. That is the single most valuable file
 * in the system and it had no lock on it.
 */
class CustomerExportScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name, ?int $createdBy = null): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => '07700900'.random_int(100, 999),
            'type' => 'customer',
            'created_by' => $createdBy,
        ]);
    }

    private function salesUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function rows(string $csv): array
    {
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        return array_slice($lines, 1); // drop the header row
    }

    public function test_a_sales_agent_exports_only_their_own_book(): void
    {
        $mine = $this->salesUser();
        $theirs = $this->salesUser();

        $this->makeCustomer('My Shop', $mine->id);
        $this->makeCustomer('Their Shop', $theirs->id);
        $this->makeCustomer('Nobodys Shop');

        $response = $this->actingAs($mine, 'sanctum')->get('/api/customers/export');
        $response->assertOk();

        $rows = $this->rows($response->streamedContent());

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('My Shop', $rows[0]);
    }

    public function test_a_role_without_the_customer_book_cannot_export_it(): void
    {
        $role = Role::create([
            'name' => 'Support',
            'nav_permissions' => ['dashboard' => true, 'tickets' => true],
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->makeCustomer('Somebody Else');

        $this->actingAs($user, 'sanctum')
            ->get('/api/customers/export')
            ->assertStatus(403);
    }

    public function test_an_admin_still_gets_the_whole_book(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->makeCustomer('One');
        $this->makeCustomer('Two');

        $response = $this->actingAs($admin, 'sanctum')->get('/api/customers/export');
        $response->assertOk();

        $this->assertCount(2, $this->rows($response->streamedContent()));
    }

    public function test_asking_for_prospects_without_prospect_access_is_refused(): void
    {
        $role = Role::create([
            'name' => 'Customers only',
            'nav_permissions' => ['dashboard' => true, 'customers' => true],
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->get('/api/customers/export?type=prospect')
            ->assertStatus(403);
    }

    public function test_a_bulk_download_of_the_book_is_recorded(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->makeCustomer('One');

        $this->actingAs($admin, 'sanctum')->get('/api/customers/export')->assertOk();

        $log = AuditLog::where('action', 'customers.exported')->first();

        $this->assertNotNull($log, 'A bulk export of the customer book should leave a trace.');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(1, $log->new_values['rows']);
    }
}
