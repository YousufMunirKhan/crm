<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives "why did we lose it" an answer the database can count.
 *
 * `leads.lost_reason` is free text, and out of 571 leads exactly one row has
 * anything in it - because marking a lead lost costs a typed sentence while
 * marking it won costs nothing, so staff mark everything won and move on. The
 * result is 391 won against 3 lost, which is not a sales record, it is a filing
 * habit, and every win rate and funnel in the product is reporting on it.
 *
 * The code column is what the picker writes and what reporting groups on.
 * `lost_reason` stays exactly as it is and keeps holding the detail somebody
 * typed, so nothing that reads it today changes and no existing row is touched.
 *
 * `lead_items` gets both. A product line closed as lost has always asked for a
 * reason and then written it into an activity description - readable by a
 * person, invisible to a query - so line-level loss has never been countable at
 * all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lost_reason_code', 40)->nullable()->after('lost_reason')->index();
        });

        Schema::table('lead_items', function (Blueprint $table) {
            $table->text('lost_reason')->nullable()->after('status');
            $table->string('lost_reason_code', 40)->nullable()->after('lost_reason')->index();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['lost_reason_code']);
            $table->dropColumn('lost_reason_code');
        });

        Schema::table('lead_items', function (Blueprint $table) {
            $table->dropIndex(['lost_reason_code']);
            $table->dropColumn(['lost_reason', 'lost_reason_code']);
        });
    }
};
