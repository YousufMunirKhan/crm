<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 20)->default('prospect')->after('id');
        });

        // Index for fast filtering on prospect/customer lists
        Schema::table('customers', function (Blueprint $table) {
            $table->index('type');
        });

        // Backfill: set type = 'customer' where customer has at least one won lead
        DB::table('customers')
            ->whereIn('id', DB::table('leads')->where('stage', 'won')->select('customer_id'))
            ->update(['type' => 'customer']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
