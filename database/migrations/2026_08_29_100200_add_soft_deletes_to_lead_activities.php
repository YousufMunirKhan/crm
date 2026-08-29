<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead activities follow their lead. With leads soft-deleted, a hard database
 * cascade no longer fires, so activities need their own deleted_at in order to
 * disappear from follow-up lists alongside the lead - and come back on restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lead_activities') || Schema::hasColumn('lead_activities', 'deleted_at')) {
            return;
        }

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lead_activities') || ! Schema::hasColumn('lead_activities', 'deleted_at')) {
            return;
        }

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
