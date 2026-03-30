<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('appointment_status')->default('pending')->after('meta'); // pending, completed, cancelled, no_show, rescheduled
            $table->text('outcome_notes')->nullable()->after('appointment_status');
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn(['assigned_user_id', 'appointment_status', 'outcome_notes']);
        });
    }
};
