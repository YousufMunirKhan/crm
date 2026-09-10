<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a shift nobody ever clocked out of.
 *
 * A quarter of all attendance is sitting open, the oldest since February, and
 * the count grows every day. Those rows are read as "still on shift" by the
 * live map, and they never get an hours figure, so the attendance report is
 * missing a quarter of its days.
 *
 * Note what this column is not: a check-out time. It would have been easy to
 * write check_out_at as check_in_at plus eight hours and have every report
 * balance - and every one of those numbers would be invented, on a record that
 * decides what somebody is paid. The shift stops being open; the hours stay
 * unknown, because they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->timestamp('auto_closed_at')->nullable()->after('check_out_location_source');
            $table->index(['check_out_at', 'auto_closed_at'], 'attendance_open_shift_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_open_shift_index');
            $table->dropColumn('auto_closed_at');
        });
    }
};
