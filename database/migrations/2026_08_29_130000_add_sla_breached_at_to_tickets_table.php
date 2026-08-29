<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a ticket's SLA breach was escalated, so the check runs once per
 * ticket rather than re-notifying on every scheduled pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets') || Schema::hasColumn('tickets', 'sla_breached_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_breached_at')->nullable()->after('sla_due_at')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'sla_breached_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('sla_breached_at');
        });
    }
};
