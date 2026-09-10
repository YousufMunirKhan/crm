<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FixedWorkLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Nothing in a test should be reaching a geocoder.
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_somebody_with_a_fixed_address_can_clock_in_without_a_device_reading(): void
    {
        $user = $this->makeUser([
            'fixed_work_latitude' => 24.8964,
            'fixed_work_longitude' => 66.9976,
            'fixed_work_location_name' => 'Naval Colony, Karachi',
        ]);

        Sanctum::actingAs($user);

        $this->post('/api/hr/attendance/check-in', [
            'photo' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertCreated();

        $this->assertDatabaseHas('attendance', [
            'user_id' => $user->id,
            'check_in_location_name' => 'Naval Colony, Karachi',
            'check_in_location_source' => 'fixed',
        ]);
    }

    public function test_the_fixed_reading_is_not_dressed_up_as_a_measurement(): void
    {
        $user = $this->makeUser([
            'fixed_work_latitude' => 24.8964,
            'fixed_work_longitude' => 66.9976,
            'fixed_work_location_name' => 'Naval Colony, Karachi',
        ]);

        Sanctum::actingAs($user);
        $this->post('/api/hr/attendance/check-in', ['photo' => UploadedFile::fake()->image('proof.jpg')])
            ->assertCreated();

        $attendance = \App\Modules\HR\Models\Attendance::where('user_id', $user->id)->firstOrFail();

        // An accuracy figure would imply somebody measured something.
        $this->assertNull($attendance->check_in_location_accuracy);
        $this->assertEquals(24.8964, (float) $attendance->check_in_latitude);
    }

    public function test_a_real_reading_still_wins_over_the_fixed_one(): void
    {
        $user = $this->makeUser([
            'fixed_work_latitude' => 24.8964,
            'fixed_work_longitude' => 66.9976,
            'fixed_work_location_name' => 'Naval Colony, Karachi',
        ]);

        Sanctum::actingAs($user);

        $this->post('/api/hr/attendance/check-in', [
            'photo' => UploadedFile::fake()->image('proof.jpg'),
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'location_name' => 'Birmingham',
            'accuracy' => 12.5,
        ])->assertCreated();

        $this->assertDatabaseHas('attendance', [
            'user_id' => $user->id,
            'check_in_location_name' => 'Birmingham',
            'check_in_location_source' => 'gps',
        ]);
    }

    public function test_without_a_fixed_address_a_missing_reading_is_still_refused(): void
    {
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $this->post('/api/hr/attendance/check-in', [
            'photo' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertStatus(400);

        $this->assertDatabaseMissing('attendance', ['user_id' => $user->id]);
    }

    public function test_clocking_out_records_the_source_too(): void
    {
        $user = $this->makeUser([
            'fixed_work_latitude' => 24.8964,
            'fixed_work_longitude' => 66.9976,
            'fixed_work_location_name' => 'Naval Colony, Karachi',
        ]);

        Sanctum::actingAs($user);

        $this->post('/api/hr/attendance/check-in', ['photo' => UploadedFile::fake()->image('in.jpg')])
            ->assertCreated();
        $this->post('/api/hr/attendance/check-out', ['photo' => UploadedFile::fake()->image('out.jpg')])
            ->assertOk();

        $this->assertDatabaseHas('attendance', [
            'user_id' => $user->id,
            'check_out_location_source' => 'fixed',
        ]);
    }

    private function makeUser(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);

        return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
    }
}
