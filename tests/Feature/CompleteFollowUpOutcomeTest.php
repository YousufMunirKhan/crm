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
 * Completing an appointment asked whether a sale happened and had no way at all
 * to say it was lost, so a dead deal either sat in the pipeline as open or got
 * ticked as won to clear it off the list. The reason picker is the same one the
 * lead page and the appointments screen already use.
 */
class CompleteFollowUpOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);
        $this->user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($this->user, 'sanctum');
    }

    private function lead(): Lead
    {
        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $this->user->id,
        ]);
    }

    private function appointment(Lead $lead): LeadActivity
    {
        return LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'assigned_user_id' => $this->user->id,
            'type' => 'appointment',
            'description' => 'Site visit',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'appointment_status' => LeadActivity::APPOINTMENT_STATUS_PENDING,
        ]);
    }

    public function test_a_lost_appointment_can_finally_be_recorded_as_lost(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Seen the demo, went elsewhere.',
            'outcome' => 'lost',
            'lost_reason_code' => 'price',
        ])->assertOk();

        $lead->refresh();
        $this->assertSame('lost', $lead->stage);
        $this->assertSame('price', $lead->lost_reason_code);
        $this->assertSame('Price', $lead->lost_reason);
    }

    public function test_losing_it_still_needs_a_reason(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Not going ahead.',
            'outcome' => 'lost',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason_code');

        $this->assertSame('lead', $lead->refresh()->stage);
    }

    public function test_a_reason_that_needs_detail_is_not_accepted_bare(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Not going ahead.',
            'outcome' => 'lost',
            'lost_reason_code' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason');

        $this->assertSame('lead', $lead->refresh()->stage);
    }

    public function test_the_detail_is_kept_beside_the_label(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Went to a rival.',
            'outcome' => 'lost',
            'lost_reason_code' => 'competitor',
            'lost_reason' => 'Signed with Dojo',
        ])->assertOk();

        $this->assertSame('Went with a competitor - Signed with Dojo', $lead->refresh()->lost_reason);
    }

    public function test_marking_it_won_closes_the_lead_won(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Signed today.',
            'outcome' => 'won',
        ])->assertOk();

        $this->assertSame('won', $lead->refresh()->stage);
    }

    public function test_answering_neither_leaves_the_stage_where_it_was(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Rang them, calling back Friday.',
        ])->assertOk();

        $this->assertSame('lead', $lead->refresh()->stage);
    }

    public function test_a_lost_outcome_still_closes_the_appointment(): void
    {
        $lead = $this->lead();
        $appointment = $this->appointment($lead);

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Turned us down on the day.',
            'outcome' => 'lost',
            'lost_reason_code' => 'not_interested',
            'appointment_activity_id' => $appointment->id,
        ])->assertOk();

        $this->assertSame(
            LeadActivity::APPOINTMENT_STATUS_COMPLETED,
            $appointment->refresh()->appointment_status
        );
        $this->assertSame('lost', $lead->refresh()->stage);
    }

    public function test_the_answer_is_kept_on_the_activity_for_the_timeline(): void
    {
        $lead = $this->lead();

        $this->postJson("/api/leads/{$lead->id}/complete-followup", [
            'remarks' => 'Too expensive for them.',
            'outcome' => 'lost',
            'lost_reason_code' => 'price',
        ])->assertOk();

        $activity = LeadActivity::where('lead_id', $lead->id)
            ->where('type', 'follow_up_completed')
            ->firstOrFail();

        $this->assertSame('lost', $activity->meta['outcome']);
        $this->assertSame('price', $activity->meta['lost_reason_code']);
    }
}
