<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Modules\HR\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePhotoReplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_an_admin_can_retake_their_check_in_photo(): void
    {
        $admin = $this->makeUser('Admin');
        $attendance = $this->clockIn($admin, 'attendance-proof/old.jpg');

        Sanctum::actingAs($admin);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_in',
        ])->assertOk();

        $attendance->refresh();
        $this->assertNotSame('attendance-proof/old.jpg', $attendance->check_in_photo_path);
        Storage::disk('public')->assertExists($attendance->check_in_photo_path);
    }

    public function test_the_replaced_photo_is_still_on_disk_and_named_in_the_audit_log(): void
    {
        $admin = $this->makeUser('Admin');
        Storage::disk('public')->put('attendance-proof/old.jpg', 'the original');
        $attendance = $this->clockIn($admin, 'attendance-proof/old.jpg');

        Sanctum::actingAs($admin);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_in',
        ])->assertOk();

        // The point of the photo is that it is evidence. A replacement that
        // erased the thing it replaced would leave no way to check.
        Storage::disk('public')->assertExists('attendance-proof/old.jpg');

        $entry = AuditLog::query()->where('action', 'attendance.photo_replaced')->first();
        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame('attendance-proof/old.jpg', $entry->old_values['check_in_photo_path']);
        $this->assertSame($attendance->refresh()->check_in_photo_path, $entry->new_values['check_in_photo_path']);
    }

    public function test_ordinary_staff_cannot_retake_the_photo(): void
    {
        $sales = $this->makeUser('Sales');
        $this->clockIn($sales, 'attendance-proof/old.jpg');

        Sanctum::actingAs($sales);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_in',
        ])->assertStatus(403);
    }

    public function test_a_manager_cannot_retake_the_photo_either(): void
    {
        $manager = $this->makeUser('Manager');
        $this->clockIn($manager, 'attendance-proof/old.jpg');

        Sanctum::actingAs($manager);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_in',
        ])->assertStatus(403);
    }

    public function test_there_is_nothing_to_replace_before_clocking_in(): void
    {
        $admin = $this->makeUser('Admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_in',
        ])->assertStatus(400);
    }

    public function test_the_check_out_photo_cannot_be_set_before_clocking_out(): void
    {
        $admin = $this->makeUser('Admin');
        $this->clockIn($admin, 'attendance-proof/old.jpg');

        Sanctum::actingAs($admin);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_out',
        ])->assertStatus(400);
    }

    public function test_the_clocked_time_and_location_are_left_alone(): void
    {
        $admin = $this->makeUser('Admin');
        $attendance = $this->clockIn($admin, 'attendance-proof/old.jpg');
        $clockedAt = $attendance->check_in_at;

        Sanctum::actingAs($admin);

        $this->postJson('/api/hr/attendance/today/photo', [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'which' => 'check_in',
        ])->assertOk();

        $attendance->refresh();
        $this->assertEquals($clockedAt, $attendance->check_in_at);
        $this->assertEquals(51.5074, (float) $attendance->check_in_latitude);
        $this->assertEquals(-0.1278, (float) $attendance->check_in_longitude);
    }

    private function clockIn(User $user, string $photoPath): Attendance
    {
        return Attendance::query()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'check_in_at' => now()->subHours(2),
            'check_in_photo_path' => $photoPath,
            'check_in_latitude' => 51.5074,
            'check_in_longitude' => -0.1278,
        ]);
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
