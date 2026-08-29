<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure lic_days is stored as a string so non-numeric values are allowed.
     *
     * Uses the schema builder rather than a raw `ALTER TABLE ... MODIFY`, which
     * is MySQL-only syntax and made every test fail against the sqlite test
     * database before it reached a single assertion.
     */
    public function up(): void
    {
        if (! Schema::hasTable('customer_remote_licenses')
            || ! Schema::hasColumn('customer_remote_licenses', 'lic_days')) {
            return;
        }

        Schema::table('customer_remote_licenses', function (Blueprint $table) {
            $table->string('lic_days', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_remote_licenses')
            || ! Schema::hasColumn('customer_remote_licenses', 'lic_days')) {
            return;
        }

        Schema::table('customer_remote_licenses', function (Blueprint $table) {
            $table->integer('lic_days')->nullable()->change();
        });
    }
};
