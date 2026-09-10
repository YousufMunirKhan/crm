<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Services\HrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceNotifiesAdminsTest extends TestCase
{
    use RefreshDatabase;

    private HrService $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = app(HrService::class);
    }

    public function test_clocking_in_reaches_the_admins(): void
    {
        $admin = $this->makeUser('Admin');
        $systemAdmin = $this->makeUser('System Admin');
        $staff = $this->makeUser('Sales', 'Ali Khan');

        $this->hr->checkIn($staff->id, $this->proof());

        foreach ([$admin, $systemAdmin] as $recipient) {
            $notification = Notification::where('notifiable_id', $recipient->id)->first();
            $this->assertNotNull($notification, "nothing reached {$recipient->id}");
            $this->assertSame('attendance.check_in', $notification->type);
            $this->assertStringContainsString('Ali Khan', $notification->title);
            $this->assertStringContainsString('clocked in', $notification->title);
        }
    }

    public function test_the_message_carries_the_time_and_the_place(): void
    {
        $admin = $this->makeUser('Admin');
        $staff = $this->makeUser('Sales', 'Ali Khan');

        $this->hr->checkIn($staff->id, $this->proof(['location_name' => '3A Perry Common Road']));

        $notification = Notification::where('notifiable_id', $admin->id)->firstOrFail();
        $this->assertStringContainsString('3A Perry Common Road', $notification->message);
        $this->assertMatchesRegularExpression('/\d{1,2}:\d{2} (AM|PM)/', $notification->message);
        $this->assertSame($staff->id, $notification->data['user_id']);
    }

    public function test_clocking_out_reports_the_hours_worked(): void
    {
        $admin = $this->makeUser('Admin');
        $staff = $this->makeUser('Sales', 'Ali Khan');

        $this->hr->checkIn($staff->id, $this->proof());
        $this->hr->checkOut($staff->id, $this->proof());

        $notification = Notification::where('notifiable_id', $admin->id)
            ->where('type', 'attendance.check_out')
            ->firstOrFail();

        $this->assertStringContainsString('clocked out', $notification->title);
        $this->assertStringContainsString('Worked', $notification->message);
    }

    public function test_an_admin_is_not_told_about_their_own_shift(): void
    {
        $admin = $this->makeUser('Admin');

        $this->hr->checkIn($admin->id, $this->proof());

        $this->assertSame(0, Notification::where('notifiable_id', $admin->id)->count());
    }

    public function test_managers_and_staff_are_not_on_the_list(): void
    {
        $manager = $this->makeUser('Manager');
        $sales = $this->makeUser('Sales');
        $staff = $this->makeUser('Sales', 'Ali Khan');

        $this->hr->checkIn($staff->id, $this->proof());

        $this->assertSame(0, Notification::whereIn('notifiable_id', [$manager->id, $sales->id])->count());
    }

    public function test_a_disabled_admin_is_skipped(): void
    {
        $admin = $this->makeUser('Admin');
        $admin->update(['is_active' => false]);
        $staff = $this->makeUser('Sales', 'Ali Khan');

        $this->hr->checkIn($staff->id, $this->proof());

        $this->assertSame(0, Notification::where('notifiable_id', $admin->id)->count());
    }

    public function test_a_broken_notification_does_not_stop_somebody_clocking_in(): void
    {
        $this->makeUser('Admin');
        $staff = $this->makeUser('Sales', 'Ali Khan');

        // Whatever else is wrong, the attendance is the thing that has to land.
        \Illuminate\Support\Facades\Schema::drop('notifications');

        $attendance = $this->hr->checkIn($staff->id, $this->proof());

        $this->assertNotNull($attendance->check_in_at);
        $this->assertDatabaseHas('attendance', ['user_id' => $staff->id]);
    }

    private function proof(array $overrides = []): array
    {
        return array_merge([
            'photo_path' => 'attendance-proof/photo.jpg',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'location_name' => 'Birmingham',
            'accuracy' => 12.5,
            'captured_at' => now(),
        ], $overrides);
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
