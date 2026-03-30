<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('anydesk_rustdesk')->nullable()->after('notes');
            $table->string('passwords')->nullable()->after('anydesk_rustdesk');
            $table->string('epos_type')->nullable()->after('passwords');
            $table->integer('lic_days')->nullable()->after('epos_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['anydesk_rustdesk', 'passwords', 'epos_type', 'lic_days']);
        });
    }
};
