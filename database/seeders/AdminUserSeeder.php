<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Ensures the default admin user exists.
 * Uses firstOrCreate - never deletes or truncates users. Safe for existing DB.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        if (!$adminRole) {
            $this->command->error('Admin role not found. Please run RoleSeeder first.');
            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@switchsave.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('Admin@123'),
                'role_id' => $adminRole->id,
            ]
        );

        $this->command->info('Admin user created: admin@switchsave.com / Admin@123');
    }
}


