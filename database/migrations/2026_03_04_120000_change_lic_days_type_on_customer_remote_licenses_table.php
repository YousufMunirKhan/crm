<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure lic_days is stored as string to allow non-numeric values
        if (Schema::hasTable('customer_remote_licenses') && Schema::hasColumn('customer_remote_licenses', 'lic_days')) {
            DB::statement('ALTER TABLE `customer_remote_licenses` MODIFY `lic_days` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Best-effort revert to integer; adjust if your previous type was different
        if (Schema::hasTable('customer_remote_licenses') && Schema::hasColumn('customer_remote_licenses', 'lic_days')) {
            DB::statement('ALTER TABLE `customer_remote_licenses` MODIFY `lic_days` INT NULL');
        }
    }
};

