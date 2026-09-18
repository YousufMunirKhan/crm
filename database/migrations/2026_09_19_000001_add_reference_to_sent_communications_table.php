<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a send was about, in a form that can be looked up before sending again.
 *
 * Automated mail has to be able to answer "have I already sent this one?" -
 * a reminder for one appointment, a chase for one invoice - and this table
 * could only answer "have I emailed this customer". Without it every run of a
 * daily command would send the same reminder again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_communications', function (Blueprint $table) {
            $table->string('reference', 191)->nullable()->after('template_id');
            $table->index(['reference', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sent_communications', function (Blueprint $table) {
            $table->dropIndex(['reference', 'sent_at']);
            $table->dropColumn('reference');
        });
    }
};
