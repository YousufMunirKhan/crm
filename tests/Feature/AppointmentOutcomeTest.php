<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 35 of 39 appointments in this system sit at "pending" forever, so held rate
 * and no-show rate cannot be measured and the calendar reads as though things
 * are still ahead when they happened weeks ago. The cause is reachability: the
 * appointments screen shows one date at a time, so once the day passes the only
 * way back to an appointment is to guess its date.
 */
class AppointmentOutcomeTest extends TestCase
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

    private function appointment(string $date, string $status = 'pending'): LeadActivity
    {
        $customer = Customer::create([
            'name' => 'Contact '.random_int(1, 99999),
            'phone' => '07700900'.random_int(100, 999),
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'stage' => 'lead',
            'assigned_to' => $this->user->id,
        ]);

        return LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'assigned_user_id' => $this->user->id,
            'type' => 'appointment',
            'description' => 'Site visit',
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'appointment_status' => $status,
        ]);
    }

    public function test_past_appointments_still_pending_are_listed_without_guessing_the_date(): void
    {
        $stale = $this->appointment(now()->subDays(21)->toDateString());
        $this->appointment(now()->subDays(5)->toDateString(), 'completed');
        $this->appointment(now()->addDays(2)->toDateString());

        $response = $this->getJson('/api/appointments?needs_outcome=1')->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertSame([$stale->id], $ids);
    }

    public function test_todays_appointment_is_not_yet_awaiting_an_outcome(): void
    {
        $this->appointment(now()->toDateString());

        $this->getJson('/api/appointments?needs_outcome=1')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_one_tap_records_what_happened(): void
    {
        $appointment = $this->appointment(now()->subDays(3)->toDateString());

        $this->putJson("/api/appointments/{$appointment->id}", ['appointment_status' => 'no_show'])
            ->assertOk();

        $this->assertSame('no_show', $appointment->fresh()->appointment_status);

        $this->getJson('/api/appointments?needs_outcome=1')->assertOk()->assertJsonCount(0);
    }

    public function test_the_morning_notification_counts_what_the_screen_shows(): void
    {
        $this->appointment(now()->subDays(10)->toDateString());
        $this->appointment(now()->subDays(4)->toDateString());
        $this->appointment(now()->addDay());

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $raised = Notification::where('type', 'appointments.needs_outcome')->first();

        $this->assertNotNull($raised);
        $this->assertStringContainsString('2 appointments need an outcome', $raised->title);
        $this->assertSame('/appointments', $raised->data['route']);
    }

    public function test_a_manager_sees_the_teams_unclosed_appointments_not_only_their_own(): void
    {
        $colleague = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null])->id,
            'is_active' => true,
        ]);

        $theirs = $this->appointment(now()->subDays(6)->toDateString());
        $theirs->forceFill(['assigned_user_id' => $colleague->id, 'user_id' => $colleague->id])->saveQuietly();
        $theirs->lead->update(['assigned_to' => $colleague->id]);

        $manager = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Manager'], ['nav_permissions' => null])->id,
            'is_active' => true,
        ]);

        // The owner's dashboard counts the whole company. Landing here and
        // seeing nothing, because the screen was strictly personal, is the tile
        // promising a number it cannot show.
        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/appointments?needs_outcome=1')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/appointments?needs_outcome=1&mine=1')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_a_salesperson_still_only_sees_their_own(): void
    {
        $colleague = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null])->id,
            'is_active' => true,
        ]);

        $theirs = $this->appointment(now()->subDays(6)->toDateString());
        $theirs->forceFill(['assigned_user_id' => $colleague->id, 'user_id' => $colleague->id])->saveQuietly();
        $theirs->lead->update(['assigned_to' => $colleague->id]);

        $this->getJson('/api/appointments?needs_outcome=1')->assertOk()->assertJsonCount(0);
    }

    public function test_closing_a_lead_lost_from_an_appointment_still_needs_a_reason(): void
    {
        $appointment = $this->appointment(now()->subDay());

        // This path wrote straight to the lead and skipped the picker entirely,
        // which would have left a second way to lose a deal with nothing
        // recorded against it.
        $this->putJson("/api/appointments/{$appointment->id}", [
            'appointment_status' => 'completed',
            'lead_stage' => 'lost',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason_code');

        $this->putJson("/api/appointments/{$appointment->id}", [
            'appointment_status' => 'completed',
            'lead_stage' => 'lost',
            'lost_reason_code' => 'price',
        ])->assertOk();

        $lead = $appointment->fresh()->lead;

        $this->assertSame('lost', $lead->stage);
        $this->assertSame('price', $lead->lost_reason_code);
        $this->assertSame('Price', $lead->lost_reason);
    }
}
