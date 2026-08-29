<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Filament back-office panel at /admin.
 *
 * The panel's value over the SPA is that it enforces policies automatically on
 * every action, so the tests that matter most here are the negative ones.
 */
class FilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_panel_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_spa_catch_all_does_not_swallow_the_panel(): void
    {
        // The SPA catch-all in web.php matches /{any}; if /admin is not
        // excluded the panel silently renders the Vue shell instead.
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertDontSee('id="app"', false);
    }

    public function test_panel_pages_are_installable_as_a_pwa(): void
    {
        // One installed home-screen app must cover both surfaces, so the
        // panel needs the same manifest and service worker as the SPA.
        $html = $this->get('/admin/login')->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('/manifest.json', $html);
        $this->assertStringContainsString('apple-touch-icon', $html);
        $this->assertStringContainsString('service-worker.js', $html);
    }

    public function test_management_roles_may_access_the_panel(): void
    {
        foreach (['Admin', 'System Admin', 'Manager'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get('/admin')
                ->assertSuccessful();
        }
    }

    public function test_field_roles_are_denied_the_panel(): void
    {
        // Sales and call agents work in the SPA; the panel exposes payroll,
        // settings and cost prices.
        foreach (['Sales', 'CallAgent', 'Support', 'Customer'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get('/admin')
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_the_panel_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_product_list_renders_for_a_manager(): void
    {
        Product::create(['name' => 'Retail ePOS', 'unit_price' => 500, 'is_active' => true]);

        $this->actingAs($this->userWithRole('Manager'))
            ->get('/admin/products')
            ->assertSuccessful()
            ->assertSee('Retail ePOS');
    }

    public function test_expense_list_renders_for_a_manager(): void
    {
        $this->actingAs($this->userWithRole('Manager'))
            ->get('/admin/expenses')
            ->assertSuccessful();
    }
}
