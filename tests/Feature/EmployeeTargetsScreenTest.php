<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\EmployeeTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The screen for setting targets could only show you somebody who already had
 * one, and the list it merged with covers sales roles only - so the owner and
 * the manager, who between them create more leads than half the sales team,
 * could not be given a target from it at all.
 */
class EmployeeTargetsScreenTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName, string $name): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'name' => $name]);
    }

    private function month(): string
    {
        return now(config('app.display_timezone'))->format('Y-m');
    }

    public function test_everybody_active_is_listed_even_with_no_target(): void
    {
        $admin = $this->user('Admin', 'The Owner');
        $this->user('Sales', 'A Rep');
        $this->user('Manager', 'The Manager');
        $this->user('Support', 'Support Person');

        $names = collect($this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/employee-targets?month='.$this->month())
            ->assertOk()
            ->json('data'))
            ->pluck('user.name')
            ->all();

        foreach (['The Owner', 'A Rep', 'The Manager', 'Support Person'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function test_somebody_who_has_left_is_not_listed(): void
    {
        $admin = $this->user('Admin', 'The Owner');
        $gone = $this->user('Sales', 'Left Last Month');
        $gone->update(['is_active' => false]);

        $names = collect($this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/employee-targets?month='.$this->month())
            ->assertOk()
            ->json('data'))
            ->pluck('user.name')
            ->all();

        $this->assertNotContains('Left Last Month', $names);
    }

    public function test_a_row_without_a_target_says_so_and_reads_as_zeroes(): void
    {
        $admin = $this->user('Admin', 'The Owner');
        $rep = $this->user('Sales', 'A Rep');

        $row = collect($this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/employee-targets?month='.$this->month())
            ->assertOk()
            ->json('data'))
            ->firstWhere('user_id', $rep->id);

        $this->assertFalse($row['has_target']);
        $this->assertSame(0, $row['target_daily_leads']);
        $this->assertSame(0, $row['target_appointments']);
    }

    public function test_a_daily_lead_target_can_be_set_and_comes_back(): void
    {
        $admin = $this->user('Admin', 'The Owner');
        $rep = $this->user('Sales', 'A Rep');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$rep->id}", [
                'month' => $this->month(),
                'target_appointments' => 10,
                'target_sales' => 5,
                'target_daily_leads' => 7,
            ])
            ->assertOk();

        $this->assertSame(7, EmployeeTarget::where('user_id', $rep->id)->first()->target_daily_leads);

        $row = collect($this->actingAs($admin, 'sanctum')
            ->getJson('/api/hr/employee-targets?month='.$this->month())
            ->assertOk()
            ->json('data'))
            ->firstWhere('user_id', $rep->id);

        $this->assertTrue($row['has_target']);
        $this->assertSame(7, $row['target_daily_leads']);
    }

    public function test_the_owner_can_be_given_a_target_like_anybody_else(): void
    {
        $admin = $this->user('Admin', 'The Owner');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$admin->id}", [
                'month' => $this->month(),
                'target_daily_leads' => 5,
            ])
            ->assertOk();

        $this->assertSame(5, EmployeeTarget::where('user_id', $admin->id)->first()->target_daily_leads);
    }

    public function test_a_rep_still_only_sees_their_own_row(): void
    {
        $rep = $this->user('Sales', 'A Rep');
        $this->user('Sales', 'Another Rep');

        $rows = $this->actingAs($rep, 'sanctum')
            ->getJson('/api/hr/employee-targets?month='.$this->month())
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($rep->id, $rows[0]['user_id']);
    }

    public function test_a_rep_still_cannot_set_targets(): void
    {
        $rep = $this->user('Sales', 'A Rep');

        $this->actingAs($rep, 'sanctum')
            ->putJson("/api/hr/employee-targets/{$rep->id}", [
                'month' => $this->month(),
                'target_daily_leads' => 99,
            ])
            ->assertForbidden();
    }
}
