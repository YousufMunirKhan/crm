<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ensures the default admin user exists.
 *
 * Uses firstOrCreate - never deletes or truncates users. Safe for an existing DB.
 *
 * The password is no longer a published constant. It comes from
 * ADMIN_SEED_PASSWORD, or is generated randomly and printed once, so a fresh
 * install does not ship with credentials that are in the README and in git
 * history.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        if (! $adminRole) {
            $this->command->error('Admin role not found. Please run RoleSeeder first.');

            return;
        }

        $email = env('ADMIN_SEED_EMAIL', 'admin@switchsave.com');
        $configured = env('ADMIN_SEED_PASSWORD');
        $password = $configured ?: Str::password(20);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Admin',
                'password' => Hash::make($password),
                'role_id' => $adminRole->id,
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $this->command->info("Admin user already exists: {$email} (password unchanged).");

            return;
        }

        $this->command->info("Admin user created: {$email}");

        if ($configured) {
            $this->command->info('Password taken from ADMIN_SEED_PASSWORD.');

            return;
        }

        $this->command->warn('Generated password (shown once - store it now): '.$password);
    }
}
