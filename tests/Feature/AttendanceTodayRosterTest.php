<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTodayRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_separates_on_shift_from_finished_from_never_started(): void
    {
        $admin = $this->makeUser('Admin', 'The Admin');
        $working = $this->makeUser('Sales', 'Still Working');
        $done = $this->makeUser('Sales', 'Gone Home');
        $absent = $this->makeUser('Sales', 'Never Arrived');

        $this->clockIn($working);
        $this->clockOut($this->clockIn($done));

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/hr/attendance/today-roster')->assertOk();

        $response->assertJsonPath('counts.on_shift', 1);
        $response->assertJsonPath('counts.finished', 1);
        $response->assertJsonPath('on_shift.0.name', 'Still Working');
        $response->assertJsonPath('finished.0.name', 'Gone Home');

        // The admin has not clocked in either, so both they and the absent one
        // are on that list - who is missing is part of the answer.
        $notIn = collect($response->json('not_in'))->pluck('name');
        $this->assertTrue($notIn->contains('Never Arrived'));
        $this->assertTrue($notIn->contains('The Admin'));
    }

    public function test_someone_on_shift_carries_their_start_and_where_from(): void
    {
        $admin = $this->makeUser('Admin');
        $working = $this->makeUser('Sales', 'Still Working');
        $this->clockIn($working);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/hr/attendance/today-roster')->assertOk();

        $this->assertNotNull($response->json('on_shift.0.checked_in_at'));
        $this->assertSame('Birmingham', $response->json('on_shift.0.location_name'));
        $this->assertGreaterThanOrEqual(0, $response->json('on_shift.0.minutes_on_shift'));
    }

    public function test_a_manager_may_see_it(): void
    {
        $manager = $this->makeUser('Manager');

        Sanctum::actingAs($manager);
        $this->getJson('/api/hr/attendance/today-roster')->assertOk();
    }

    public function test_ordinary_staff_may_not(): void
    {
        $sales = $this->makeUser('Sales');

        Sanctum::actingAs($sales);
        $this->getJson('/api/hr/attendance/today-roster')->assertStatus(403);
    }

    public function test_a_disabled_account_is_left_off_the_roster(): void
    {
        $admin = $this->makeUser('Admin');
        $gone = $this->makeUser('Sales', 'Left The Company');
        $gone->update(['is_active' => false]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/hr/attendance/today-roster')->assertOk();

        $everyone = collect($response->json('on_shift'))
            ->concat($response->json('finished'))
            ->concat($response->json('not_in'))
            ->pluck('name');

        $this->assertFalse($everyone->contains('Left The Company'));
    }

    public function test_yesterdays_attendance_does_not_count_as_today(): void
    {
        $admin = $this->makeUser('Admin');
        $staff = $this->makeUser('Sales', 'Worked Yesterday');

        Attendance::query()->create([
            'user_id' => $staff->id,
            'date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/hr/attendance/today-roster')->assertOk();

        $response->assertJsonPath('counts.on_shift', 0);
        $this->assertTrue(collect($response->json('not_in'))->pluck('name')->contains('Worked Yesterday'));
    }

    private function clockIn(User $user): Attendance
    {
        return Attendance::query()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHours(3),
            'check_in_location_name' => 'Birmingham',
        ]);
    }

    private function clockOut(Attendance $attendance): Attendance
    {
        $attendance->update([
            'check_out_at' => now()->subHour(),
            'work_hours' => 2,
        ]);

        return $attendance;
    }

    private function makeUser(string $roleName, ?string $name = null): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(array_filter([
            'role_id' => $role->id,
            'name' => $name,
        ]));
    }
}
