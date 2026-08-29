<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-tests every generated Filament resource.
 *
 * Resources are generated from the database schema, so a column rename or a
 * bad recordTitleAttribute breaks them at render time rather than at lint
 * time. This is the cheapest way to catch that.
 */
class FilamentResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public static function resourcePaths(): array
    {
        return [
            'products' => ['/admin/products'],
            'product categories' => ['/admin/product-categories'],
            'customers' => ['/admin/customers'],
            'leads' => ['/admin/leads'],
            'tickets' => ['/admin/tickets'],
            'invoices' => ['/admin/invoices'],
            'users' => ['/admin/users'],
            'salaries' => ['/admin/salaries'],
            'attendances' => ['/admin/attendances'],
            'expenses' => ['/admin/expenses'],
            'employee targets' => ['/admin/employee-targets'],
            'email templates' => ['/admin/email-templates'],
            'message templates' => ['/admin/message-templates'],
            'contact consents' => ['/admin/contact-consents'],
            'roles' => ['/admin/roles'],
            'import logs' => ['/admin/import-logs'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('resourcePaths')]
    public function test_resource_list_page_renders(string $path): void
    {
        $this->actingAs($this->admin())
            ->get($path)
            ->assertSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('resourcePaths')]
    public function test_resource_create_page_renders(string $path): void
    {
        $response = $this->actingAs($this->admin())->get($path.'/create');

        // Some resources are intentionally read-only (no create page); a 404
        // there is fine, a 500 is not.
        $this->assertContains(
            $response->status(),
            [200, 403, 404],
            $path.'/create returned '.$response->status()
        );
    }

    public function test_dashboard_renders_with_all_resources_registered(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertSuccessful();
    }
}
