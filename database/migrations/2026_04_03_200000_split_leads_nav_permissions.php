<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $migrate = function ($row) {
            $np = $row->nav_permissions;
            if (! is_array($np) || ! array_key_exists('leads_pipeline', $np)) {
                return;
            }
            $v = (bool) $np['leads_pipeline'];
            unset($np['leads_pipeline']);
            $np['all_leads'] = $v;
            $np['lead_pipeline'] = $v;
            $row->nav_permissions = $np;
            $row->saveQuietly();
        };

        Role::query()->whereNotNull('nav_permissions')->each($migrate);
        User::query()->whereNotNull('nav_permissions')->each($migrate);
    }

    public function down(): void
    {
        $rollback = function ($row) {
            $np = $row->nav_permissions;
            if (! is_array($np)) {
                return;
            }
            if (! array_key_exists('all_leads', $np) && ! array_key_exists('lead_pipeline', $np)) {
                return;
            }
            $any = ! empty($np['all_leads']) || ! empty($np['lead_pipeline']);
            unset($np['all_leads'], $np['lead_pipeline']);
            if ($any) {
                $np['leads_pipeline'] = true;
            }
            $row->nav_permissions = $np === [] ? null : $np;
            $row->saveQuietly();
        };

        Role::query()->whereNotNull('nav_permissions')->each($rollback);
        User::query()->whereNotNull('nav_permissions')->each($rollback);
    }
};
