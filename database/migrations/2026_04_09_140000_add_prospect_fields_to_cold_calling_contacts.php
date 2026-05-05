<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cold_calling_contacts', function (Blueprint $table) {
            $table->timestamp('prospect_marked_at')->nullable()->after('last_seen_at');
            $table->string('prospect_stage', 32)->nullable()->after('prospect_marked_at');
            $table->foreignId('assigned_to')->nullable()->after('prospect_stage')->constrained('users')->nullOnDelete();
            $table->foreignId('crm_customer_id')->nullable()->after('assigned_to')->constrained('customers')->nullOnDelete();
            $table->index('prospect_marked_at');
        });
    }

    public function down(): void
    {
        Schema::table('cold_calling_contacts', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['crm_customer_id']);
            $table->dropIndex(['prospect_marked_at']);
            $table->dropColumn(['prospect_marked_at', 'prospect_stage', 'assigned_to', 'crm_customer_id']);
        });
    }
};
