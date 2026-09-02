<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\EmployeeLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Location readings taken during a shift.
 *
 * The property worth defending is that a reading outside an open shift is
 * refused by the server. "We only track during working hours" is either
 * something the system enforces or a promise made by an app on somebody else's
 * phone - an app that can be an old build, or wrong, or simply left running.
 * A point recorded at nine in the evening is what turns this from an
 * operational tool into a liability, so the refusal is tested here rather than
 * trusted to the client.
 */
class EmployeeLocationTest extends TestCase
{
    use RefreshDatabase;

    private function rep(): User
    {
        $role = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function clockIn(User $user, ?string $at = null): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'check_in_at' => $at ?? now()->subHours(2),
        ]);
    }

    /** @param array<int, array<string, mixed>> $points */
    private function sendPoints(User $user, array $points)
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/hr/attendance/location', ['points' => $points, 'source' => 'app']);
    }

    private function point(string $when, float $lat = 52.4862, float $lng = -1.8904): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => 12.5,
            'recorded_at' => $when,
            'battery_level' => 74,
        ];
    }

    // ───────────────────────────────────────────── the rule that matters

    public function test_nothing_is_recorded_when_the_person_is_not_clocked_in(): void
    {
        $rep = $this->rep();

        $this->sendPoints($rep, [$this->point(now()->toIso8601String())])
            ->assertStatus(409)
            ->assertJson(['tracking' => false]);

        $this->assertDatabaseCount('employee_locations', 0);
    }

    public function test_nothing_is_recorded_after_clocking_out(): void
    {
        $rep = $this->rep();
        $shift = $this->clockIn($rep);

        $this->sendPoints($rep, [$this->point(now()->subHour()->toIso8601String())])->assertCreated();

        $shift->update(['check_out_at' => now()->subMinutes(30)]);

        // The evening ping. A phone left running, or an old build of the app,
        // must not be able to add to the trail once the shift is closed.
        $this->sendPoints($rep, [$this->point(now()->toIso8601String())])
            ->assertStatus(409)
            ->assertJson(['tracking' => false]);

        $this->assertSame(1, EmployeeLocation::count());
    }

    public function test_a_reading_from_before_the_shift_started_is_dropped(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep, now()->subHour()->toDateTimeString());

        $response = $this->sendPoints($rep, [
            $this->point(now()->subHours(3)->toIso8601String()),   // before clock-in
            $this->point(now()->subMinutes(20)->toIso8601String()), // during
        ])->assertCreated();

        $this->assertSame(1, $response->json('stored'));
        $this->assertSame(1, $response->json('rejected'));
        $this->assertSame(1, EmployeeLocation::count());
    }

    public function test_a_reading_from_the_future_is_dropped(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep);

        // A phone with a wrong clock, not a time traveller.
        $response = $this->sendPoints($rep, [$this->point(now()->addHours(2)->toIso8601String())])
            ->assertCreated();

        $this->assertSame(0, $response->json('stored'));
        $this->assertSame(1, $response->json('rejected'));
    }

    // ───────────────────────────────────────────── the offline backlog

    public function test_a_phone_that_was_offline_can_send_its_backlog(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep);

        $response = $this->sendPoints($rep, [
            $this->point(now()->subMinutes(60)->toIso8601String()),
            $this->point(now()->subMinutes(45)->toIso8601String()),
            $this->point(now()->subMinutes(30)->toIso8601String()),
            $this->point(now()->subMinutes(15)->toIso8601String()),
        ])->assertCreated();

        // A basement or a dead signal is the normal case, not the exception.
        // Without batching the trail is full of holes that look like somebody
        // switching it off.
        $this->assertSame(4, $response->json('stored'));
    }

    public function test_resending_the_same_backlog_does_not_double_the_trail(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep);

        $batch = [
            $this->point(now()->subMinutes(30)->toIso8601String()),
            $this->point(now()->subMinutes(15)->toIso8601String()),
        ];

        $this->sendPoints($rep, $batch)->assertCreated();
        // The phone never saw our acknowledgement, so it sends again.
        $this->sendPoints($rep, $batch)->assertCreated();

        $this->assertSame(2, EmployeeLocation::count());
    }

    // ───────────────────────────────────────────── who may read a trail

    public function test_a_colleague_cannot_read_somebody_elses_movements(): void
    {
        $rep = $this->rep();
        $colleague = $this->rep();

        $this->actingAs($rep, 'sanctum')
            ->getJson("/api/hr/attendance/location/{$colleague->id}")
            ->assertStatus(403);
    }

    public function test_a_person_can_read_their_own(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep);
        $this->sendPoints($rep, [$this->point(now()->subMinutes(10)->toIso8601String())]);

        $this->actingAs($rep, 'sanctum')
            ->getJson("/api/hr/attendance/location/{$rep->id}")
            ->assertOk()
            ->assertJsonCount(1, 'points');
    }

    public function test_a_manager_can_read_the_team(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep);
        $this->sendPoints($rep, [$this->point(now()->subMinutes(10)->toIso8601String())]);

        $managerRole = Role::firstOrCreate(['name' => 'Manager'], ['nav_permissions' => null]);
        $manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/hr/attendance/location/{$rep->id}")
            ->assertOk()
            ->assertJsonPath('points.0.battery_level', 74);
    }

    // ───────────────────────────────────────────── quality of a reading

    public function test_a_vague_reading_is_marked_rather_than_hidden(): void
    {
        $rep = $this->rep();
        $this->clockIn($rep);

        $vague = $this->point(now()->subMinutes(10)->toIso8601String());
        $vague['accuracy'] = 2000; // a phone indoors, on cell towers alone

        $this->sendPoints($rep, [$vague])->assertCreated();

        // Two kilometres is not a location. Plotted beside a 5-metre fix it
        // looks identical, and a straight line between them reads as a journey
        // that never happened - so it is flagged, not silently drawn.
        $this->actingAs($rep, 'sanctum')
            ->getJson("/api/hr/attendance/location/{$rep->id}")
            ->assertOk()
            ->assertJsonPath('points.0.usable', false);
    }

    // ───────────────────────────────────── a phone that has stopped talking

    public function test_a_silent_phone_is_visible_rather_than_looking_like_an_idle_person(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'Manager'], ['nav_permissions' => null]);
        $manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $reporting = $this->rep();
        $silent = $this->rep();

        $shiftA = $this->clockIn($reporting);
        $shiftB = $this->clockIn($silent);

        EmployeeLocation::create([
            'user_id' => $reporting->id, 'attendance_id' => $shiftA->id,
            'latitude' => 52.4862, 'longitude' => -1.8904, 'recorded_at' => now()->subMinutes(5),
        ]);

        // Two hours ago, then nothing. On iOS this is what happens when the
        // person answers "While Using" to the background-location prompt:
        // tracking stops with no error and nothing on this end.
        EmployeeLocation::create([
            'user_id' => $silent->id, 'attendance_id' => $shiftB->id,
            'latitude' => 52.4862, 'longitude' => -1.8904, 'recorded_at' => now()->subHours(2),
        ]);

        $body = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/hr/attendance/live-map')
            ->assertOk()
            ->json();

        $this->assertSame(2, $body['on_shift']);
        $this->assertSame(1, $body['silent']);

        // Loudest problem first.
        $this->assertSame($silent->id, $body['people'][0]['user']['id']);
        $this->assertFalse($body['people'][0]['reporting']);
        $this->assertTrue($body['people'][1]['reporting']);
    }

    public function test_a_rep_cannot_see_where_the_team_is(): void
    {
        $rep = $this->rep();

        $this->actingAs($rep, 'sanctum')
            ->getJson('/api/hr/attendance/live-map')
            ->assertStatus(403);
    }

    public function test_old_trails_expire_and_the_shift_record_survives(): void
    {
        $rep = $this->rep();
        $shift = $this->clockIn($rep);

        $recent = EmployeeLocation::create([
            'user_id' => $rep->id, 'attendance_id' => $shift->id,
            'latitude' => 52.4862, 'longitude' => -1.8904,
            'recorded_at' => now()->subDays(10),
        ]);

        $ancient = EmployeeLocation::create([
            'user_id' => $rep->id, 'attendance_id' => $shift->id,
            'latitude' => 52.4862, 'longitude' => -1.8904,
            'recorded_at' => now()->subDays(200),
        ]);

        $this->artisan('crm:prune-locations')->assertSuccessful();

        // "How long do you keep it" cannot be answered with "forever" for staff
        // movement data.
        $this->assertNull(EmployeeLocation::find($ancient->id));
        $this->assertNotNull(EmployeeLocation::find($recent->id));

        // Who worked which day, and where they clocked in, is not location
        // history and is deliberately left alone.
        $this->assertNotNull($shift->fresh());
    }

    public function test_the_phone_can_ask_whether_it_should_be_recording(): void
    {
        $rep = $this->rep();

        $this->actingAs($rep, 'sanctum')
            ->getJson('/api/hr/attendance/location/status')
            ->assertOk()
            ->assertJson(['tracking' => false]);

        $this->clockIn($rep);

        $this->actingAs($rep, 'sanctum')
            ->getJson('/api/hr/attendance/location/status')
            ->assertOk()
            ->assertJson(['tracking' => true, 'interval_seconds' => 900]);
    }
}
