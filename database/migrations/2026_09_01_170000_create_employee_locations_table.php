<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a field member of staff was, while they were on shift.
 *
 * Two rules are built into the shape of this table rather than left to the
 * phone to honour:
 *
 * 1. **Every point belongs to one attendance row.** Not to a user and a
 *    timestamp - to a specific shift. A ping that cannot be attached to an open
 *    shift has nowhere to go, so "only while clocked in" is enforced by the
 *    schema and the endpoint, not promised by the app. Tracking somebody at
 *    nine in the evening is the thing that turns this from an operational tool
 *    into a liability, and a client app is the wrong place to guarantee it will
 *    not happen.
 *
 * 2. **Nothing here is derived.** Latitude, longitude, accuracy and the time
 *    the device took the reading. `recorded_at` is the phone's clock and
 *    `created_at` is ours, kept apart on purpose: a phone that was offline for
 *    an hour sends its backlog late, and the difference between the two is how
 *    you tell a delayed point from a stale one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The shift this point belongs to. Deleting a shift takes its trail
            // with it, which is what a staff member asking to have a day
            // removed would expect.
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Metres. A reading good to 2km is not a location, and without this
            // column there is no way to tell one from a reading good to 5m.
            $table->decimal('accuracy', 10, 2)->nullable();

            // The device's own clock, when it took the reading.
            $table->timestamp('recorded_at');

            // Kept because a flat battery is the usual reason a trail stops,
            // and without it every gap looks like somebody switching it off.
            $table->unsignedTinyInteger('battery_level')->nullable();

            // 'app' or 'browser', so a patchy trail from a foreground-only
            // client is never mistaken for a background one.
            $table->string('source', 16)->default('app');

            $table->timestamps();

            // The two questions asked of this table: one person's day, and one
            // shift's trail in order.
            $table->index(['user_id', 'recorded_at']);
            $table->index(['attendance_id', 'recorded_at']);

            // A phone that resends its backlog must not double up the trail.
            $table->unique(['attendance_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_locations');
    }
};
