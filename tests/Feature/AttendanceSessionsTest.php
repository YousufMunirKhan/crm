<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\AttendanceSession;
use App\Modules\HR\Services\HrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSessionsTest extends TestCase
{
    use RefreshDatabase;

    private HrService $hr;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = app(HrService::class);

        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);
        $this->staff = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_somebody_can_come_back_after_lunch(): void
    {
        // The thing that was impossible before: clocking out did not end the day.
        $this->travelTo('2026-06-10 09:00:00');
        $this->hr->checkIn($this->staff->id);

        $this->travelTo('2026-06-10 13:00:00');
        $this->hr->checkOut($this->staff->id);

        $this->travelTo('2026-06-10 14:00:00');
        $this->hr->checkIn($this->staff->id);

        $this->assertSame(2, AttendanceSession::where('user_id', $this->staff->id)->count());
    }

    public function test_the_day_reads_first_in_to_last_out(): void
    {
        $this->travelTo('2026-06-10 09:00:00');
        $this->hr->checkIn($this->staff->id);
        $this->travelTo('2026-06-10 13:00:00');
        $this->hr->checkOut($this->staff->id);
        $this->travelTo('2026-06-10 14:00:00');
        $this->hr->checkIn($this->staff->id);
        $this->travelTo('2026-06-10 18:00:00');
        $attendance = $this->hr->checkOut($this->staff->id);

        $this->assertSame('09:00', $attendance->check_in_at->format('H:i'));
        $this->assertSame('18:00', $attendance->check_out_at->format('H:i'));

        // Nine, not eight: this business counts the break as worked time. The
        // alternative is to add the sessions up, and that is a different number.
        $this->assertEquals(9.0, (float) $attendance->work_hours);
        // Two stretches - 09:00-13:00 and 14:00-18:00 - with one break between.
        $this->assertSame(2, (int) $attendance->sessions_count);
        $this->assertSame(1, (int) $attendance->breaks_count);
    }

    public function test_the_day_has_no_finish_time_while_somebody_is_still_in(): void
    {
        $this->travelTo('2026-06-10 09:00:00');
        $this->hr->checkIn($this->staff->id);
        $this->travelTo('2026-06-10 13:00:00');
        $this->hr->checkOut($this->staff->id);
        $this->travelTo('2026-06-10 14:00:00');
        $attendance = $this->hr->checkIn($this->staff->id);

        // Everything that asks who is working reads a missing clock-out as
        // "still here", and somebody back from lunch is still here.
        $this->assertNull($attendance->check_out_at);
        $this->assertEquals(0.0, (float) $attendance->work_hours);
    }

    public function test_clocking_in_twice_without_clocking_out_is_refused(): void
    {
        $this->hr->checkIn($this->staff->id);

        $this->expectExceptionMessage('You are already clocked in.');
        $this->hr->checkIn($this->staff->id);
    }

    public function test_clocking_out_when_not_in_is_refused(): void
    {
        $this->expectExceptionMessage('You are not clocked in.');
        $this->hr->checkOut($this->staff->id);
    }

    public function test_the_daily_cap_holds(): void
    {
        config(['attendance.max_sessions_per_day' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->hr->checkIn($this->staff->id);
            $this->hr->checkOut($this->staff->id);
        }

        $this->expectExceptionMessage('You have already clocked in 3 times today.');
        $this->hr->checkIn($this->staff->id);
    }

    public function test_each_session_keeps_its_own_proof(): void
    {
        $this->hr->checkIn($this->staff->id, [
            'photo_path' => 'proof/morning.jpg',
            'location_name' => 'Birmingham',
        ]);
        $this->hr->checkOut($this->staff->id, [
            'photo_path' => 'proof/lunch.jpg',
            'location_name' => 'The cafe',
        ]);
        $this->hr->checkIn($this->staff->id, [
            'photo_path' => 'proof/afternoon.jpg',
            'location_name' => 'Coventry',
        ]);

        $sessions = AttendanceSession::where('user_id', $this->staff->id)->orderBy('sequence')->get();

        $this->assertSame('proof/morning.jpg', $sessions[0]->check_in_photo_path);
        $this->assertSame('The cafe', $sessions[0]->check_out_location_name);
        $this->assertSame('Coventry', $sessions[1]->check_in_location_name);
    }

    public function test_the_day_row_carries_the_first_and_last_proof(): void
    {
        $this->hr->checkIn($this->staff->id, ['photo_path' => 'proof/first-in.jpg']);
        $this->hr->checkOut($this->staff->id, ['photo_path' => 'proof/first-out.jpg']);
        $this->hr->checkIn($this->staff->id, ['photo_path' => 'proof/second-in.jpg']);
        $attendance = $this->hr->checkOut($this->staff->id, ['photo_path' => 'proof/last-out.jpg']);

        // Every existing report reads these two fields and none of them changed.
        $this->assertSame('proof/first-in.jpg', $attendance->check_in_photo_path);
        $this->assertSame('proof/last-out.jpg', $attendance->check_out_photo_path);
    }

    public function test_an_abandoned_session_is_closed_with_the_day(): void
    {
        $this->travelTo('2026-06-10 09:00:00');
        $this->hr->checkIn($this->staff->id);

        $this->travelTo('2026-06-12 09:00:00');
        $this->artisan('crm:close-abandoned-shifts')->assertSuccessful();

        $session = AttendanceSession::where('user_id', $this->staff->id)->firstOrFail();
        $this->assertNotNull($session->auto_closed_at);
        $this->assertNull($session->check_out_at);

        $attendance = Attendance::where('user_id', $this->staff->id)->firstOrFail();
        $this->assertNotNull($attendance->auto_closed_at);
        $this->assertNull($attendance->check_out_at);
    }

    public function test_a_new_day_starts_fresh(): void
    {
        $this->travelTo('2026-06-10 09:00:00');
        $this->hr->checkIn($this->staff->id);
        $this->travelTo('2026-06-10 17:00:00');
        $this->hr->checkOut($this->staff->id);

        $this->travelTo('2026-06-11 09:00:00');
        $attendance = $this->hr->checkIn($this->staff->id);

        $this->assertSame(1, (int) $attendance->sessions_count);
        $this->assertSame(2, Attendance::where('user_id', $this->staff->id)->count());
    }
}
