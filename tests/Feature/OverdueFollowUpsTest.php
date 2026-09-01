<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every follow-up surface in this product was built to look forward: the sales
 * dashboard showed the date you picked, and the follow-ups page opened on today
 * to +7 days. So a promise made and then missed was the one thing nobody could
 * see - 33 outstanding across the company, the oldest 189 days old.
 *
 * The rep's dashboard now leads with them, which makes the scoping on this
 * endpoint load-bearing: a salesperson must see their own missed promises and
 * nobody else's.
 */
class OverdueFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function leadDue(User $owner, string $when, string $stage = 'lead'): Lead
    {
        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => $stage,
            'assigned_to' => $owner->id,
            'next_follow_up_at' => $when,
        ]);
    }

    public function test_a_rep_sees_their_own_missed_promises_and_nobody_elses(): void
    {
        $rep = $this->userWithRole('Sales');
        $colleague = $this->userWithRole('Sales');

        $mine = $this->leadDue($rep, now()->subDays(12)->toDateTimeString());
        $theirs = $this->leadDue($colleague, now()->subDays(4)->toDateTimeString());

        $response = $this->actingAs($rep, 'sanctum')->getJson('/api/followups?overdue=1')->assertOk();

        $ids = collect($response->json('data') ?? $response->json())->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_a_follow_up_still_ahead_is_not_overdue(): void
    {
        $rep = $this->userWithRole('Sales');

        $this->leadDue($rep, now()->addDays(2)->toDateTimeString());

        $response = $this->actingAs($rep, 'sanctum')->getJson('/api/followups?overdue=1')->assertOk();

        $this->assertCount(0, $response->json('data') ?? $response->json());
    }

    public function test_a_closed_lead_stops_chasing_you(): void
    {
        $rep = $this->userWithRole('Sales');

        // Won and lost leads carry old follow-up dates. Counting them would put
        // finished work at the top of somebody's morning forever.
        $this->leadDue($rep, now()->subDays(30)->toDateTimeString(), 'won');
        $this->leadDue($rep, now()->subDays(30)->toDateTimeString(), 'lost');

        $response = $this->actingAs($rep, 'sanctum')->getJson('/api/followups?overdue=1')->assertOk();

        $this->assertCount(0, $response->json('data') ?? $response->json());
    }

    public function test_a_manager_sees_the_whole_team(): void
    {
        $manager = $this->userWithRole('Manager');
        $rep = $this->userWithRole('Sales');

        $theirs = $this->leadDue($rep, now()->subDay()->toDateTimeString());

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/followups?overdue=1')->assertOk();

        $ids = collect($response->json('data') ?? $response->json())->pluck('id')->all();

        $this->assertContains($theirs->id, $ids);
    }
}
