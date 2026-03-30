<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add field to track which follow-up activity was converted to this lead
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('converted_from_activity_id')->nullable()->after('customer_id')->constrained('lead_activities')->nullOnDelete();
        });

        // Add field to track which lead this activity was converted to
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->foreignId('converted_to_lead_id')->nullable()->after('lead_id')->constrained('leads')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropForeign(['converted_to_lead_id']);
            $table->dropColumn('converted_to_lead_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['converted_from_activity_id']);
            $table->dropColumn('converted_from_activity_id');
        });
    }
};
