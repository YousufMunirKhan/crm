<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->whereIn('name', ['Admin', 'System Admin'])
            ->update(['nav_permissions' => null]);

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Admin', 'System Admin'])
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('role_id', $roleIds->all())
                ->update(['nav_permissions' => null]);
        }
    }

    public function down(): void
    {
        // No reliable rollback of cleared JSON.
    }
};
