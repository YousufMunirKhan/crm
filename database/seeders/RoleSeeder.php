<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'System Admin', 'description' => 'System Administrator (full access)'],
            ['name' => 'Admin', 'description' => 'Administrator'],
            ['name' => 'Manager', 'description' => 'Manager'],
            ['name' => 'Sales', 'description' => 'Sales Agent'],
            ['name' => 'CallAgent', 'description' => 'Call Center Agent'],
            ['name' => 'Marketing', 'description' => 'Marketing (campaigns, templates, cold calling)'],
            ['name' => 'Support', 'description' => 'Support Agent'],
            ['name' => 'HR', 'description' => 'Human Resources'],
            ['name' => 'Customer', 'description' => 'Customer'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}


