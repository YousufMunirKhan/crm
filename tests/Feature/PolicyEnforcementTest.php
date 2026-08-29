<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Product;
use App\Modules\HR\Models\Expense;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app had no authorisation framework at all: no app/Policies directory,
 * no Gate calls, and 24 of 43 controllers with no role check. These policies
 * are the single source of truth Filament enforces automatically, so the
 * negative cases are the point.
 */
class PolicyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_policies_are_actually_registered_for_module_models(): void
    {
        // Laravel's convention discovery only covers App\Models\X, and most
        // models here live under App\Modules\*\Models - so if the explicit
        // registration is ever dropped, every gate silently returns null.
        $models = [
            Product::class,
            Customer::class,
            Invoice::class,
            Expense::class,
            Setting::class,
        ];

        foreach ($models as $model) {
            $this->assertNotNull(
                \Illuminate\Support\Facades\Gate::getPolicyFor($model),
                $model.' has no registered policy'
            );
        }
    }

    public function test_sales_can_read_products_but_not_change_them(): void
    {
        $sales = $this->user('Sales');

        $this->assertTrue($sales->can('viewAny', Product::class));
        $this->assertFalse($sales->can('create', Product::class));
        $this->assertFalse($sales->can('delete', Product::class));
    }

    public function test_sales_cannot_touch_expenses_at_all(): void
    {
        $sales = $this->user('Sales');

        $this->assertFalse($sales->can('viewAny', Expense::class));
        $this->assertFalse($sales->can('create', Expense::class));
    }

    public function test_only_owners_may_change_settings(): void
    {
        // Settings hold the SMTP relay; a non-owner previously could repoint
        // all outbound mail.
        $this->assertTrue($this->user('Admin')->can('update', Setting::class));
        $this->assertTrue($this->user('System Admin')->can('update', Setting::class));
        $this->assertFalse($this->user('Manager')->can('update', Setting::class));
        $this->assertFalse($this->user('Sales')->can('update', Setting::class));
    }

    public function test_managers_cannot_delete_financial_records(): void
    {
        // Deletion is narrower than management on purpose: invoices are
        // statutory records retained for six years.
        $manager = $this->user('Manager');

        $this->assertTrue($manager->can('update', Invoice::class));
        $this->assertFalse($manager->can('delete', Invoice::class));
        $this->assertFalse($manager->can('forceDelete', Invoice::class));
    }

    public function test_only_owners_may_change_roles(): void
    {
        $this->assertTrue($this->user('Admin')->can('update', \App\Models\Role::class));
        $this->assertFalse($this->user('Manager')->can('update', \App\Models\Role::class));
    }

    public function test_marketing_role_may_manage_templates_but_not_payroll(): void
    {
        $marketing = $this->user('Marketing');

        $this->assertTrue($marketing->can('viewAny', \App\Models\EmailTemplate::class));
        $this->assertTrue($marketing->can('create', \App\Models\EmailTemplate::class));
        $this->assertFalse($marketing->can('viewAny', \App\Modules\HR\Models\Salary::class));
    }

    public function test_consent_records_are_readable_by_marketing_but_only_owners_may_edit(): void
    {
        // Consent is evidence for PECR; it should not be casually editable.
        $this->assertTrue($this->user('Marketing')->can('viewAny', \App\Models\ContactConsent::class));
        $this->assertFalse($this->user('Marketing')->can('update', \App\Models\ContactConsent::class));
        $this->assertTrue($this->user('Admin')->can('update', \App\Models\ContactConsent::class));
    }
}
