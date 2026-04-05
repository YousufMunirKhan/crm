<?php

use App\Modules\CRM\Models\LeadActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->date('appointment_date')->nullable()->after('outcome_notes');
            $table->string('appointment_time', 32)->nullable()->after('appointment_date');
        });

        LeadActivity::query()
            ->where('type', 'appointment')
            ->chunkById(200, function ($activities) {
                foreach ($activities as $a) {
                    $m = $a->meta;
                    if (! is_array($m) || empty($m['appointment_date'])) {
                        continue;
                    }
                    $a->appointment_date = $m['appointment_date'];
                    $a->appointment_time = $m['appointment_time'] ?? null;
                    $a->saveQuietly();
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
