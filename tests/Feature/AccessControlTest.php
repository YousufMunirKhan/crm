<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the Access Manager configures, and what the API actually enforces.
 *
 * Until now those were two different things. `nav.section` existed, was
 * registered, and guarded zero of the API's routes, so unchecking "Invoices"
 * for Sales hid a menu item while `/api/invoices` answered exactly as before.
 * Only 6 of 69 SPA routes checked their section either, so typing the address
 * was enough.
 *
 * The other half is per-user access. `users.nav_permissions` *replaced* the
 * role list rather than adding to it, so granting one extra section meant
 * reproducing all thirty checkboxes by hand and maintaining that copy forever.
 * Nobody did: zero rows use it, while 6 of 15 accounts are Admin.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithSections(array $allowed, string $roleName = 'Limited'): User
    {
        $role = Role::create([
            'name' => $roleName,
            'nav_permissions' => array_fill_keys($allowed, true),
        ]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- the API

    public function test_a_hidden_section_is_now_refused_by_the_api_not_just_the_menu(): void
    {
        $user = $this->userWithSections(['dashboard', 'tickets']);

        // The menu item was hidden; the endpoint behind it was not.
        $this->actingAs($user, 'sanctum')->getJson('/api/invoices')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/hr/expenses')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/hr/salaries')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/reporting/executive')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/commission-management/summary')->assertStatus(403);
    }

    public function test_a_granted_section_still_works(): void
    {
        $user = $this->userWithSections(['dashboard', 'invoices']);

        $this->actingAs($user, 'sanctum')->getJson('/api/invoices')->assertOk();
    }

    public function test_clocking_yourself_in_is_not_an_hr_permission(): void
    {
        // Your own attendance is self-service. Only everybody else's is gated,
        // and confusing the two would stop half the company starting work.
        $user = $this->userWithSections(['dashboard', 'tickets']);

        $this->actingAs($user, 'sanctum')->getJson('/api/hr/attendance/today')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/hr/attendance')->assertStatus(403);
    }

    public function test_support_can_still_look_up_a_customer_to_raise_a_ticket(): void
    {
        // Creating a ticket loads the customer list. Gating customers purely on
        // the customers section would have made ticket creation impossible for
        // the one role whose whole job is tickets.
        $user = $this->userWithSections(['dashboard', 'tickets']);

        $this->actingAs($user, 'sanctum')->getJson('/api/customers')->assertOk();
    }

    public function test_an_administrator_is_never_limited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson('/api/invoices')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/reporting/executive')->assertOk();
    }

    public function test_a_role_with_no_whitelist_is_unrestricted(): void
    {
        $role = Role::create(['name' => 'Unrestricted', 'nav_permissions' => null]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')->getJson('/api/invoices')->assertOk();
    }

    // ------------------------------------------------- per-person exceptions

    public function test_one_person_can_be_given_one_section_without_touching_their_role(): void
    {
        $user = $this->userWithSections(['dashboard', 'tickets']);
        $colleague = User::factory()->create(['role_id' => $user->role_id, 'is_active' => true]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/users/{$user->id}/access", [
                'section' => 'invoices',
                'effect' => 'grant',
                'reason' => 'Covering while Aziza is away',
            ])->assertCreated();

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/invoices')->assertOk();

        // And nobody else on that role moved.
        $this->actingAs($colleague, 'sanctum')->getJson('/api/invoices')->assertStatus(403);
    }

    public function test_temporary_access_ends_on_its_own(): void
    {
        $user = $this->userWithSections(['dashboard', 'tickets']);

        UserPermissionGrant::create([
            'user_id' => $user->id,
            'section' => 'invoices',
            'effect' => 'grant',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/invoices')->assertOk();

        $this->travel(2)->days();

        // No cleanup job has run. Expiry has to hold on its own or "temporary"
        // means "until somebody remembers".
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/invoices')->assertStatus(403);
    }

    public function test_one_person_can_be_shut_out_of_something_their_role_allows(): void
    {
        $user = $this->userWithSections(['dashboard', 'invoices']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/users/{$user->id}/access", [
                'section' => 'invoices',
                'effect' => 'revoke',
                'reason' => 'Under review',
            ])->assertCreated();

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/invoices')->assertStatus(403);
    }

    public function test_a_revoke_beats_a_grant_on_the_same_section(): void
    {
        $user = $this->userWithSections(['dashboard']);

        UserPermissionGrant::create(['user_id' => $user->id, 'section' => 'invoices', 'effect' => 'grant']);
        UserPermissionGrant::create(['user_id' => $user->id, 'section' => 'invoices', 'effect' => 'revoke']);

        // Somebody deliberately shut out must not be let back in by a stray row.
        $this->assertFalse($user->fresh()->allowsNavSection('invoices'));
    }

    public function test_granting_again_replaces_rather_than_stacks(): void
    {
        $user = $this->userWithSections(['dashboard']);
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->postJson("/api/users/{$user->id}/access", [
            'section' => 'invoices', 'effect' => 'grant',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson("/api/users/{$user->id}/access", [
            'section' => 'invoices', 'effect' => 'revoke',
        ])->assertCreated();

        // Two live rows for one section is a question with two answers.
        $this->assertSame(1, $user->permissionGrants()->active()->where('section', 'invoices')->count());
        $this->assertFalse($user->fresh()->allowsNavSection('invoices'));
    }

    public function test_switching_one_off_early_keeps_the_record(): void
    {
        $user = $this->userWithSections(['dashboard']);
        $admin = $this->admin();

        $grant = UserPermissionGrant::create([
            'user_id' => $user->id, 'section' => 'invoices', 'effect' => 'grant',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$user->id}/access/{$grant->id}")
            ->assertOk();

        $grant->refresh();

        $this->assertNotNull($grant->revoked_at, 'The row should be switched off, not deleted.');
        $this->assertSame($admin->id, $grant->revoked_by);
        $this->assertFalse($user->fresh()->allowsNavSection('invoices'));
    }

    public function test_only_administrators_can_change_access(): void
    {
        $user = $this->userWithSections(['dashboard', 'tickets']);
        $victim = $this->userWithSections(['dashboard'], 'Other');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$victim->id}/access", ['section' => 'invoices', 'effect' => 'grant'])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$victim->id}/access")
            ->assertStatus(403);
    }

    public function test_an_invented_section_is_refused(): void
    {
        $user = $this->userWithSections(['dashboard']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/users/{$user->id}/access", ['section' => 'everything', 'effect' => 'grant'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('section');
    }

    public function test_nobody_can_be_shut_out_of_the_dashboard(): void
    {
        $user = $this->userWithSections(['dashboard']);

        // It is where the app opens. Taking it away leaves somebody signed in
        // with nowhere to land.
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/users/{$user->id}/access", ['section' => 'dashboard', 'effect' => 'revoke'])
            ->assertStatus(422);
    }

    public function test_an_expiry_in_the_past_is_refused(): void
    {
        $user = $this->userWithSections(['dashboard']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/users/{$user->id}/access", [
                'section' => 'invoices',
                'effect' => 'grant',
                'expires_at' => now()->subDay()->toIso8601String(),
            ])->assertStatus(422)->assertJsonValidationErrors('expires_at');
    }

    // ------------------------------------------------ one answer, not two

    public function test_the_api_tells_the_client_what_it_may_see(): void
    {
        $user = $this->userWithSections(['dashboard', 'tickets']);

        UserPermissionGrant::create([
            'user_id' => $user->id, 'section' => 'invoices', 'effect' => 'grant',
        ]);

        // The SPA used to work this out for itself and had already drifted from
        // the server's version. Now it is told.
        $response = $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/auth/me')->assertOk();

        $sections = $response->json('nav_sections');

        $this->assertTrue($sections['tickets']);
        $this->assertTrue($sections['invoices'], 'A per-user grant should reach the client.');
        $this->assertFalse($sections['expenses']);
        $this->assertTrue($sections['dashboard']);
    }

    public function test_the_access_screen_says_where_each_answer_came_from(): void
    {
        $user = $this->userWithSections(['dashboard', 'tickets']);

        UserPermissionGrant::create([
            'user_id' => $user->id, 'section' => 'invoices', 'effect' => 'grant',
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/users/{$user->id}/access")
            ->assertOk();

        $bySection = collect($response->json('sections'))->keyBy('key');

        $this->assertSame('granted', $bySection['invoices']['source']);
        $this->assertSame('role', $bySection['tickets']['source']);
        $this->assertSame('role', $bySection['expenses']['source']);
        $this->assertFalse($bySection['expenses']['allowed']);
    }
}
