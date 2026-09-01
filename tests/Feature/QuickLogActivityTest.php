<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logging a call has to be one request with no navigation.
 *
 * Across 571 live leads the company has recorded three calls, ever - not
 * because the calls are not happening, but because writing one down cost a page
 * load, a hunt for a button and a required notes field. Everything downstream
 * rests on this: "untouched for 30 days" cannot tell a neglected lead from an
 * unrecorded one while the record is empty.
 */
class QuickLogActivityTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function lead(User $owner): Lead
    {
        $customer = Customer::create([
            'name' => 'Quick Log',
            'phone' => '07700905001',
            'email' => 'quick@example.com',
            'type' => Customer::TYPE_PROSPECT,
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
        ]);
    }

    public function test_a_call_is_recorded_in_one_request(): void
    {
        $user = $this->salesUser();
        $lead = $this->lead($user);

        $this->actingAs($user)
            ->postJson("/api/leads/{$lead->id}/activity", [
                'type' => 'call',
                'description' => 'Called and spoke to them.',
                'meta' => ['outcome' => 'positive', 'quick' => true],
            ])
            ->assertSuccessful();

        $activity = LeadActivity::where('lead_id', $lead->id)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('call', $activity->type);
    }

    public function test_a_no_answer_is_recorded_as_its_own_outcome(): void
    {
        $user = $this->salesUser();
        $lead = $this->lead($user);

        $this->actingAs($user)
            ->postJson("/api/leads/{$lead->id}/activity", [
                'type' => 'call',
                'description' => 'Called, no answer.',
                'meta' => ['outcome' => 'no_answer', 'quick' => true],
            ])
            ->assertSuccessful();

        $this->assertSame(
            'no_answer',
            LeadActivity::where('lead_id', $lead->id)->latest('id')->first()->meta['outcome'] ?? null,
        );
    }

    /**
     * The point of logging is that the lead stops looking untouched, so the
     * stale-lead views have to see it.
     */
    public function test_logging_moves_the_lead_off_the_untouched_list(): void
    {
        $user = $this->salesUser();
        $lead = $this->lead($user);

        $lead->forceFill(['updated_at' => now()->subDays(60)])->saveQuietly();
        $this->assertTrue($lead->fresh()->updated_at->lt(now()->subDays(30)));

        $this->actingAs($user)
            ->postJson("/api/leads/{$lead->id}/activity", [
                'type' => 'call',
                'description' => 'Called and spoke to them.',
                'meta' => ['outcome' => 'positive', 'quick' => true],
            ])
            ->assertSuccessful();

        $this->assertTrue(
            $lead->fresh()->updated_at->gt(now()->subMinute()),
            'Logging a call should refresh the lead, or the stale filters keep hiding it.',
        );
    }

    public function test_an_unknown_activity_type_is_refused(): void
    {
        $user = $this->salesUser();
        $lead = $this->lead($user);

        $this->actingAs($user)
            ->postJson("/api/leads/{$lead->id}/activity", [
                'type' => 'telepathy',
                'description' => 'Thought about them.',
            ])
            ->assertStatus(422);
    }
}
