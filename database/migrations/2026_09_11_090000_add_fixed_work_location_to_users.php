<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A work address for people whose device will not give one.
 *
 * Clocking in requires coordinates, so somebody whose browser refuses
 * geolocation - an office desktop, a phone with location off - cannot record
 * attendance at all. Their record simply stops.
 *
 * A fixed address per person fills that gap. It is stored against the user
 * rather than hard-coded anywhere, so it is a setting rather than a special
 * case in the code, and the attendance row records which of the two it came
 * from. That last part is the important one: the location on an attendance
 * record is evidence, and evidence that cannot be told apart from a GPS
 * reading when it is not one is worse than no evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('fixed_work_latitude', 10, 7)->nullable()->after('is_active');
            $table->decimal('fixed_work_longitude', 10, 7)->nullable()->after('fixed_work_latitude');
            $table->string('fixed_work_location_name')->nullable()->after('fixed_work_longitude');
        });

        Schema::table('attendance', function (Blueprint $table) {
            // 'gps' when the device gave a reading, 'fixed' when it fell back to
            // the person's set work address. Null on every row written before
            // this existed, which is honest: nobody knows about those.
            $table->string('check_in_location_source', 20)->nullable()->after('check_in_location_captured_at');
            $table->string('check_out_location_source', 20)->nullable()->after('check_out_location_captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fixed_work_latitude', 'fixed_work_longitude', 'fixed_work_location_name']);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['check_in_location_source', 'check_out_location_source']);
        });
    }
};
