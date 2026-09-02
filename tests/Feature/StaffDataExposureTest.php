<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three things any signed-in member of staff could read or do, found while
 * assessing whether this could be sold as a product. All three were live.
 *
 * They are grouped because they share a cause: an endpoint that was written for
 * one trusted office and never revisited once the app had roles.
 */
class StaffDataExposureTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(array_merge(
            ['role_id' => $role->id, 'is_active' => true],
            $attributes
        ));
    }

    // ────────────────────────────────── the SMTP password and the API keys

    public function test_a_salesperson_cannot_read_the_credentials(): void
    {
        Setting::create(['key' => 'smtp_password', 'value' => 'hunter2']);
        Setting::create(['key' => 'sms_api_key', 'value' => 'sk-live-abc']);
        Setting::create(['key' => 'company_name', 'value' => 'Switch & Save']);

        $body = $this->actingAs($this->userWithRole('Sales'), 'sanctum')
            ->getJson('/api/settings')
            ->assertOk()
            ->json();

        // Writing these was gated to admins all along. Reading was not.
        $this->assertArrayNotHasKey('smtp_password', $body);
        $this->assertArrayNotHasKey('sms_api_key', $body);

        // The settings the app actually needs still come through.
        $this->assertSame('Switch & Save', $body['company_name']);
    }

    public function test_an_administrator_still_gets_them(): void
    {
        Setting::create(['key' => 'smtp_password', 'value' => 'hunter2']);

        $this->actingAs($this->userWithRole('Admin'), 'sanctum')
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('smtp_password', 'hunter2');
    }

    public function test_a_credential_added_later_is_secret_without_anyone_remembering(): void
    {
        Setting::create(['key' => 'stripe_secret_key', 'value' => 'sk_live_xyz']);

        // An allow-list would have leaked this until somebody updated a list.
        $body = $this->actingAs($this->userWithRole('Sales'), 'sanctum')
            ->getJson('/api/settings')->assertOk()->json();

        $this->assertArrayNotHasKey('stripe_secret_key', $body);
    }

    public function test_a_single_credential_cannot_be_fetched_by_key_either(): void
    {
        Setting::create(['key' => 'smtp_password', 'value' => 'hunter2']);

        $this->actingAs($this->userWithRole('Sales'), 'sanctum')
            ->getJson('/api/settings/smtp_password')
            ->assertStatus(403);
    }

    // ────────────────────────────────────────── colleagues' bank details

    public function test_the_user_list_does_not_carry_bank_details(): void
    {
        $this->userWithRole('Sales', [
            'bank_account_number' => '12345678',
            'bank_sort_code' => '01-02-03',
        ]);

        // This endpoint feeds assignee dropdowns in two dozen places, so it
        // cannot be locked down - the columns had to go instead.
        $body = $this->actingAs($this->userWithRole('Sales'), 'sanctum')
            ->getJson('/api/users')
            ->assertOk()
            ->json();

        $encoded = json_encode($body);

        $this->assertStringNotContainsString('12345678', $encoded);
        $this->assertStringNotContainsString('01-02-03', $encoded);
    }

    public function test_the_employee_list_does_not_carry_them_either(): void
    {
        $this->userWithRole('Sales', ['bank_account_number' => '87654321']);

        $body = $this->actingAs($this->userWithRole('Manager'), 'sanctum')
            ->getJson('/api/hr/employees')
            ->assertOk()
            ->json();

        $this->assertStringNotContainsString('87654321', json_encode($body));
    }

    public function test_a_person_can_still_see_their_own_bank_details(): void
    {
        $rep = $this->userWithRole('Sales', ['bank_account_number' => '11112222']);

        // Their own employee page is the place these are meant to be readable,
        // and that route checks access before answering.
        $this->actingAs($rep, 'sanctum')
            ->getJson("/api/users/{$rep->id}")
            ->assertOk()
            ->assertJsonPath('bank_account_number', '11112222');
    }

    // ──────────────────────────────────────────────── leavers keep working

    public function test_a_deactivated_person_cannot_log_in(): void
    {
        $leaver = $this->userWithRole('Sales', [
            'email' => 'leaver@example.test',
            'password' => bcrypt('correct-horse'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'leaver@example.test',
            'password' => 'correct-horse',
        ])->assertStatus(422);

        $this->assertSame(0, $leaver->tokens()->count());
    }

    public function test_a_token_issued_before_deactivation_stops_working(): void
    {
        $leaver = $this->userWithRole('Sales');

        $this->actingAs($leaver, 'sanctum')->getJson('/api/users')->assertOk();

        $leaver->update(['is_active' => false]);

        // Marking somebody inactive used to remove the back office and nothing
        // else - the SPA and the whole API kept working on the token they held.
        $this->actingAs($leaver->fresh(), 'sanctum')
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_an_active_person_is_unaffected(): void
    {
        $rep = $this->userWithRole('Sales', [
            'email' => 'still.here@example.test',
            'password' => bcrypt('correct-horse'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'still.here@example.test',
            'password' => 'correct-horse',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }
}
