<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Signing in used to delete every token the user had, on every device, so on
 * a CRM that ships as an installable PWA a salesperson logging in on their
 * phone silently signed themselves out on their desktop.
 */
class MultiDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

    private function user(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Sales'], ['description' => 'Sales']);

        return User::factory()->create([
            'role_id' => $role->id,
            'email' => 'agent@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
    }

    private function login(string $userAgent)
    {
        return $this->withHeaders(['User-Agent' => $userAgent])
            ->postJson('/api/auth/login', [
                'email' => 'agent@example.com',
                'password' => 'correct-horse-battery-staple',
            ]);
    }

    public function test_signing_in_on_a_phone_does_not_sign_you_out_on_desktop(): void
    {
        $user = $this->user();

        $desktopToken = $this->login(self::DESKTOP_UA)->assertOk()->json('token');
        $this->login(self::PHONE_UA)->assertOk();

        // The desktop token must still work after the phone login.
        $this->withHeaders([
            'Authorization' => 'Bearer '.$desktopToken,
            'User-Agent' => self::DESKTOP_UA,
        ])->getJson('/api/auth/me')->assertOk();

        $this->assertSame(2, $user->fresh()->tokens()->count());
    }

    public function test_logging_in_twice_on_the_same_device_does_not_accumulate_tokens(): void
    {
        $user = $this->user();

        $this->login(self::DESKTOP_UA)->assertOk();
        $this->login(self::DESKTOP_UA)->assertOk();

        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_the_older_token_for_the_same_device_stops_working(): void
    {
        $this->user();

        $first = $this->login(self::DESKTOP_UA)->assertOk()->json('token');
        $this->login(self::DESKTOP_UA)->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$first,
            'User-Agent' => self::DESKTOP_UA,
        ])->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_wrong_credentials_are_still_rejected(): void
    {
        $this->user();

        $this->withHeaders(['User-Agent' => self::DESKTOP_UA])
            ->postJson('/api/auth/login', [
                'email' => 'agent@example.com',
                'password' => 'wrong',
            ])->assertStatus(422);
    }
}
