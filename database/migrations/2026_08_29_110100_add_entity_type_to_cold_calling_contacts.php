<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PECR's corporate-subscriber exemption covers limited companies and LLPs, but
 * NOT sole traders or ordinary partnerships - which is most of what a Google
 * Places sweep returns. Without recording which one a contact is, there is no
 * field to gate marketing on, and no TPS/CTPS screening record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cold_calling_contacts')) {
            return;
        }

        Schema::table('cold_calling_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('cold_calling_contacts', 'entity_type')) {
                // corporate | sole_trader | partnership | unknown
                $table->string('entity_type', 24)->default('unknown')->index();
            }
            if (! Schema::hasColumn('cold_calling_contacts', 'do_not_call')) {
                $table->boolean('do_not_call')->default(false)->index();
            }
            if (! Schema::hasColumn('cold_calling_contacts', 'tps_status')) {
                // unscreened | clear | listed
                $table->string('tps_status', 16)->default('unscreened')->index();
            }
            if (! Schema::hasColumn('cold_calling_contacts', 'tps_screened_at')) {
                $table->timestamp('tps_screened_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cold_calling_contacts')) {
            return;
        }

        Schema::table('cold_calling_contacts', function (Blueprint $table) {
            foreach (['entity_type', 'do_not_call', 'tps_status', 'tps_screened_at'] as $column) {
                if (Schema::hasColumn('cold_calling_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
