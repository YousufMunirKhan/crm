<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many leads a day this person is expected to put on the board.
 *
 * The other three targets on this row are monthly and about what closed;
 * this is the one asked for every morning, and until now it lived in a config
 * file as one number for everybody - so it could not be set for a person at
 * all, and the screen that sets targets had nowhere to put it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_targets', function (Blueprint $table) {
            $table->unsignedInteger('target_daily_leads')->default(0)->after('target_sales');
        });

        // Anybody already carrying a target this month was being measured on the
        // flat five that used to live in config, so they keep it. Past months are
        // left alone: there was no daily lead target then, and inventing one now
        // would put a number on records of what already happened.
        DB::table('employee_targets')
            ->where('month', '>=', now(config('app.display_timezone', 'Europe/London'))->format('Y-m'))
            ->update(['target_daily_leads' => (int) config('leads.daily_target', 5)]);
    }

    public function down(): void
    {
        Schema::table('employee_targets', function (Blueprint $table) {
            $table->dropColumn('target_daily_leads');
        });
    }
};
