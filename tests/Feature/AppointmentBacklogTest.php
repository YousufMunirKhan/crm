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
 * Two dozen past appointments sat pending for months because the only way to
 * close one was a tap on a row you first had to find, and the screen showed one
 * date at a time. The list now opens on the most recent regardless of date, and
 * the backlog can be cleared in one go.
 */
class AppointmentBacklogTest extends TestCase
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

    private function appointment(string $date, string $status = 'pending', ?User $owner = null): LeadActivity
    {
        $owner ??= $this->user;

        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $owner->id,
        ]);

        return LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $owner->id,
            'assigned_user_id' => $owner->id,
            'type' => 'appointment',
            'description' => 'Site visit',
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'appointment_status' => $status,
        ]);
    }

    public function test_the_whole_backlog_closes_in_one_go(): void
    {
        $first = $this->appointment(now()->subDays(30)->toDateString());
        $second = $this->appointment(now()->subDays(3)->toDateString());

        $this->postJson('/api/appointments/close-pending', ['appointment_status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('closed', 2);

        $this->assertSame('completed', $first->refresh()->appointment_status);
        $this->assertSame('completed', $second->refresh()->appointment_status);
        $this->getJson('/api/appointments?needs_outcome=1')->assertOk()->assertJsonCount(0);
    }

    public function test_it_leaves_alone_what_is_not_overdue_or_not_pending(): void
    {
        $future = $this->appointment(now()->addDays(2)->toDateString());
        $today = $this->appointment(now()->toDateString());
        $alreadyNoShow = $this->appointment(now()->subDays(5)->toDateString(), 'no_show');

        $this->postJson('/api/appointments/close-pending')->assertOk()->assertJsonPath('closed', 0);

        $this->assertSame('pending', $future->refresh()->appointment_status);
        $this->assertSame('pending', $today->refresh()->appointment_status);
        $this->assertSame('no_show', $alreadyNoShow->refresh()->appointment_status);
    }

    public function test_closing_the_backlog_does_not_touch_the_leads(): void
    {
        $appointment = $this->appointment(now()->subDays(9)->toDateString());
        $appointment->lead->update(['stage' => 'quotation']);

        $this->postJson('/api/appointments/close-pending')->assertOk();

        $this->assertSame('quotation', $appointment->refresh()->lead->stage);
    }

    public function test_a_salesperson_cannot_close_somebody_elses(): void
    {
        $colleague = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null])->id,
            'is_active' => true,
        ]);
        $theirs = $this->appointment(now()->subDays(6)->toDateString(), 'pending', $colleague);

        $this->postJson('/api/appointments/close-pending')->assertOk()->assertJsonPath('closed', 0);

        $this->assertSame('pending', $theirs->refresh()->appointment_status);
    }

    public function test_a_manager_clears_the_teams_backlog_but_can_limit_it_to_their_own(): void
    {
        $colleague = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null])->id,
            'is_active' => true,
        ]);
        $theirs = $this->appointment(now()->subDays(6)->toDateString(), 'pending', $colleague);

        $manager = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Manager'], ['nav_permissions' => null])->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/appointments/close-pending', ['mine' => 1])
            ->assertOk()
            ->assertJsonPath('closed', 0);
        $this->assertSame('pending', $theirs->refresh()->appointment_status);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/appointments/close-pending')
            ->assertOk()
            ->assertJsonPath('closed', 1);
        $this->assertSame('completed', $theirs->refresh()->appointment_status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $appointment = $this->appointment(now()->subDays(4)->toDateString());

        $this->postJson('/api/appointments/close-pending', ['appointment_status' => 'won'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('appointment_status');

        $this->assertSame('pending', $appointment->refresh()->appointment_status);
    }

    public function test_without_a_date_the_list_is_the_most_recent_first(): void
    {
        $old = $this->appointment(now()->subDays(20)->toDateString(), 'completed');
        $newest = $this->appointment(now()->subDays(1)->toDateString(), 'completed');
        $middle = $this->appointment(now()->subDays(10)->toDateString(), 'completed');

        $ids = collect($this->getJson('/api/appointments')->assertOk()->json())->pluck('id')->all();

        $this->assertSame([$newest->id, $middle->id, $old->id], $ids);
    }

    public function test_the_recent_list_is_capped(): void
    {
        foreach (range(1, 4) as $days) {
            $this->appointment(now()->subDays($days)->toDateString(), 'completed');
        }

        $this->getJson('/api/appointments?limit=2')->assertOk()->assertJsonCount(2);
    }

    public function test_the_list_still_carries_the_lead_stage_so_a_result_can_be_shown(): void
    {
        $appointment = $this->appointment(now()->subDays(2)->toDateString(), 'completed');
        $appointment->lead->update(['stage' => 'won']);

        $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonPath('0.appointment_status', 'completed')
            ->assertJsonPath('0.lead.stage', 'won');
    }
}
