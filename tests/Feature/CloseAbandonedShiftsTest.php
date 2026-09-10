<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseAbandonedShiftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shift_left_open_too_long_is_closed(): void
    {
        $shift = $this->openShift(hoursAgo: 30);

        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $this->assertNotNull($shift->refresh()->auto_closed_at);
    }

    public function test_no_finish_time_is_invented(): void
    {
        $shift = $this->openShift(hoursAgo: 30);

        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $shift->refresh();

        // This is the whole point. A made-up check-out would balance every
        // report and decide somebody's pay on a number nobody recorded.
        //
        // work_hours is checked against 0 rather than null because the column
        // has always defaulted to 0 - the assertion that matters is that
        // closing the shift did not put hours into it.
        $this->assertNull($shift->check_out_at);
        $this->assertEquals(0.0, (float) $shift->work_hours);
    }

    public function test_a_long_day_is_still_a_day(): void
    {
        $shift = $this->openShift(hoursAgo: 12);

        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $this->assertNull($shift->refresh()->auto_closed_at);
    }

    public function test_the_threshold_can_be_overridden(): void
    {
        $shift = $this->openShift(hoursAgo: 12);

        $this->artisan('crm:close-abandoned-shifts', ['--hours' => 8])->assertSuccessful();

        $this->assertNotNull($shift->refresh()->auto_closed_at);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $shift = $this->openShift(hoursAgo: 30);

        $this->artisan('crm:close-abandoned-shifts', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($shift->refresh()->auto_closed_at);
    }

    public function test_a_shift_somebody_closed_properly_is_left_alone(): void
    {
        $shift = $this->openShift(hoursAgo: 30);
        $shift->update(['check_out_at' => now()->subHours(22), 'work_hours' => 8]);

        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $shift->refresh();
        $this->assertNull($shift->auto_closed_at);
        $this->assertEquals(8, (float) $shift->work_hours);
    }

    public function test_running_it_twice_does_not_restamp_the_first_run(): void
    {
        $shift = $this->openShift(hoursAgo: 30);

        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();
        $first = $shift->refresh()->auto_closed_at;

        $this->travel(2)->hours();
        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $this->assertEquals($first, $shift->refresh()->auto_closed_at);
    }

    public function test_an_abandoned_shift_stops_counting_as_on_shift(): void
    {
        $admin = $this->makeUser('Admin');
        $shift = $this->openShift(hoursAgo: 30);

        \Laravel\Sanctum\Sanctum::actingAs($admin);

        // Same day, so the roster would otherwise have them working.
        $shift->update(['date' => now()->toDateString()]);
        $this->getJson('/api/hr/attendance/today-roster')->assertJsonPath('counts.on_shift', 1);

        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $response = $this->getJson('/api/hr/attendance/today-roster')->assertOk();
        $response->assertJsonPath('counts.on_shift', 0);
        $response->assertJsonPath('finished.0.never_clocked_out', true);
        $this->assertNull($response->json('finished.0.checked_out_at'));
    }

    private function openShift(int $hoursAgo): Attendance
    {
        $user = $this->makeUser('Sales');

        return Attendance::query()->create([
            'user_id' => $user->id,
            'date' => now()->subHours($hoursAgo)->toDateString(),
            'check_in_at' => now()->subHours($hoursAgo),
        ]);
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
