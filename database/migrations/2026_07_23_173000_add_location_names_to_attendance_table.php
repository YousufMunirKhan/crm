<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('check_in_location_name', 500)->nullable()->after('check_in_longitude');
            $table->string('check_out_location_name', 500)->nullable()->after('check_out_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_location_name',
                'check_out_location_name',
            ]);
        });
    }
};
