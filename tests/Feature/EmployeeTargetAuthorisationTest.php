<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\EmployeeTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setting somebody's target is a management act, and the write had no check of
 * any kind - neither on the route nor in the controller. Any signed-in member
 * of staff could rewrite anyone's numbers, on any month, including their own.
 * The sibling GET has always narrowed non-managers to their own row, so only
 * the write was open.
 *
 * This matters more now than it did: the DNA merchant work makes targets the
 * spine of how people are measured, and a measure anybody can edit is not a
 * measure.
 */
class EmployeeTargetAuthorisationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function payload(int $sales = 99): array
    {
        return [
            'month' => now()->format('Y-m'),
            'target_appointments' => 5,
            'target_sales' => $sales,
        ];
    }

    public function test_a_salesperson_cannot_set_their_own_target(): void
    {
        $rep = $this->userWithRole('Sales');

        $this->actingAs($rep, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$rep->id}", $this->payload())
            ->assertStatus(403);

        $this->assertDatabaseCount('employee_targets', 0);
    }

    public function test_a_salesperson_cannot_set_somebody_elses_target(): void
    {
        $rep = $this->userWithRole('Sales');
        $colleague = $this->userWithRole('Sales');

        $this->actingAs($rep, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$colleague->id}", $this->payload())
            ->assertStatus(403);

        $this->assertDatabaseCount('employee_targets', 0);
    }

    public function test_support_cannot_either(): void
    {
        $support = $this->userWithRole('Support');
        $rep = $this->userWithRole('Sales');

        $this->actingAs($support, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$rep->id}", $this->payload())
            ->assertStatus(403);
    }

    public function test_a_manager_can_set_a_target(): void
    {
        $manager = $this->userWithRole('Manager');
        $rep = $this->userWithRole('Sales');

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$rep->id}", $this->payload(12))
            ->assertSuccessful();

        $this->assertSame(
            12,
            (int) EmployeeTarget::where('user_id', $rep->id)->value('target_sales')
        );
    }

    public function test_an_admin_can_set_a_target(): void
    {
        $admin = $this->userWithRole('Admin');
        $rep = $this->userWithRole('Sales');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$rep->id}", $this->payload(8))
            ->assertSuccessful();

        $this->assertSame(
            8,
            (int) EmployeeTarget::where('user_id', $rep->id)->value('target_sales')
        );
    }

    public function test_reading_targets_still_narrows_a_rep_to_their_own(): void
    {
        $rep = $this->userWithRole('Sales');
        $colleague = $this->userWithRole('Sales');
        $month = now()->format('Y-m');

        EmployeeTarget::create(['user_id' => $rep->id, 'month' => $month, 'target_sales' => 3]);
        EmployeeTarget::create(['user_id' => $colleague->id, 'month' => $month, 'target_sales' => 7]);

        $rows = $this->actingAs($rep, 'sanctum')
            ->getJson('/api/hr/employee-targets?month='.$month)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
    }
}
