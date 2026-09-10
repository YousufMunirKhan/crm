<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each time somebody clocks in and out during a day.
 *
 * Attendance was one row per person per day: one in, one out, and a unique key
 * making sure of it. Clock out for lunch and you were finished - there was no
 * way back in, and no way for anybody to put the afternoon back. Twenty-two
 * shifts on this system are under an hour long, and a hundred and forty were
 * never closed at all, which is what people do when clocking out costs them the
 * rest of the day.
 *
 * The day row stays exactly as it was, and every report that reads it carries
 * on working - first in, last out, hours, all still there. This table sits
 * underneath it holding the detail, and the day row is recalculated from it
 * whenever a session closes. That was the point of doing it this way round:
 * eight places read the day row, and every one of them would have had to change
 * if days had become many rows instead of one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');

            $table->timestamp('check_in_at');
            $table->string('check_in_photo_path')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->string('check_in_location_name')->nullable();
            $table->decimal('check_in_location_accuracy', 10, 2)->nullable();
            $table->string('check_in_location_source', 20)->nullable();

            $table->timestamp('check_out_at')->nullable();
            $table->string('check_out_photo_path')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->string('check_out_location_name')->nullable();
            $table->decimal('check_out_location_accuracy', 10, 2)->nullable();
            $table->string('check_out_location_source', 20)->nullable();

            $table->timestamp('auto_closed_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'sequence']);
            $table->index(['user_id', 'check_out_at']);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->unsignedTinyInteger('sessions_count')->default(0)->after('work_hours');
            // Breaks rather than sessions because it is the question people
            // actually ask. Three sessions is two breaks.
            $table->unsignedTinyInteger('breaks_count')->default(0)->after('sessions_count');
        });

        // Every day already recorded becomes a single session, so the history
        // reads the same way as everything written from here on. Without this
        // the detail view would be empty for every day before today and look
        // like the records had been lost.
        DB::table('attendance')
            ->whereNotNull('check_in_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $now = now();

                $sessions = collect($rows)->map(fn ($row) => [
                    'attendance_id' => $row->id,
                    'user_id' => $row->user_id,
                    'sequence' => 1,
                    'check_in_at' => $row->check_in_at,
                    'check_in_photo_path' => $row->check_in_photo_path,
                    'check_in_latitude' => $row->check_in_latitude,
                    'check_in_longitude' => $row->check_in_longitude,
                    'check_in_location_name' => $row->check_in_location_name,
                    'check_in_location_accuracy' => $row->check_in_location_accuracy,
                    'check_in_location_source' => $row->check_in_location_source,
                    'check_out_at' => $row->check_out_at,
                    'check_out_photo_path' => $row->check_out_photo_path,
                    'check_out_latitude' => $row->check_out_latitude,
                    'check_out_longitude' => $row->check_out_longitude,
                    'check_out_location_name' => $row->check_out_location_name,
                    'check_out_location_accuracy' => $row->check_out_location_accuracy,
                    'check_out_location_source' => $row->check_out_location_source,
                    'auto_closed_at' => $row->auto_closed_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('attendance_sessions')->insert($sessions);
            });

        DB::table('attendance')->whereNotNull('check_in_at')->update(['sessions_count' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['sessions_count', 'breaks_count']);
        });
    }
};
