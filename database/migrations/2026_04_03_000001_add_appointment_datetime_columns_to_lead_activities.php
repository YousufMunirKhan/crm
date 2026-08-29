<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfills appointment_date/appointment_time from the meta JSON.
     *
     * Uses the query builder rather than the LeadActivity model: a model
     * reflects today's schema (including the soft-delete scope added later),
     * which does not exist yet at this point in the migration sequence.
     */
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->date('appointment_date')->nullable()->after('outcome_notes');
            $table->string('appointment_time', 32)->nullable()->after('appointment_date');
        });

        DB::table('lead_activities')
            ->where('type', 'appointment')
            ->orderBy('id')
            ->chunk(200, function ($activities) {
                foreach ($activities as $activity) {
                    $meta = $activity->meta;

                    if (is_string($meta)) {
                        $meta = json_decode($meta, true);
                    }

                    if (! is_array($meta) || empty($meta['appointment_date'])) {
                        continue;
                    }

                    DB::table('lead_activities')
                        ->where('id', $activity->id)
                        ->update([
                            'appointment_date' => $meta['appointment_date'],
                            'appointment_time' => $meta['appointment_time'] ?? null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropColumn(['appointment_date', 'appointment_time']);
        });
    }
};
