<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadDeleteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_cannot_delete_lead(): void
    {
        $admin = $this->makeUser('Admin');
        $sales = $this->makeUser('Sales');
        $lead = $this->makeLead($admin);

        Sanctum::actingAs($sales);
        $this->deleteJson("/api/leads/{$lead->id}")->assertStatus(403);
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_manager_cannot_delete_lead(): void
    {
        $admin = $this->makeUser('Admin');
        $manager = $this->makeUser('Manager');
        $lead = $this->makeLead($admin);

        Sanctum::actingAs($manager);
        $this->deleteJson("/api/leads/{$lead->id}")->assertStatus(403);
    }

    public function test_admin_can_delete_lead_and_removes_activities(): void
    {
        $admin = $this->makeUser('Admin');
        $lead = $this->makeLead($admin);
        LeadActivity::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $admin->id,
            'type' => 'note',
            'description' => 'Test note',
        ]);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/leads/{$lead->id}")->assertNoContent();

        // Leads are soft-deleted so commercial history survives; the row remains with deleted_at set.
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
        $this->assertSoftDeleted('lead_activities', ['lead_id' => $lead->id]);
    }

    public function test_system_admin_can_delete_lead(): void
    {
        $sys = $this->makeUser('System Admin');
        $lead = $this->makeLead($sys);

        Sanctum::actingAs($sys);
        $this->deleteJson("/api/leads/{$lead->id}")->assertNoContent();
        // Leads are soft-deleted so commercial history survives; the row remains with deleted_at set.
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function makeLead(User $creator): Lead
    {
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'phone' => '123',
            'type' => Customer::TYPE_PROSPECT,
        ]);

        return Lead::query()->create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $creator->id,
            'created_by' => $creator->id,
            'pipeline_value' => 100,
        ]);
    }
}
