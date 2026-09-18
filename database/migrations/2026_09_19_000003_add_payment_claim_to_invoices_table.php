<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I have already paid this", said by the customer from the chase email.
 *
 * The weekly chase has no way to be told it is wrong. Somebody who paid by
 * bank transfer on the due date, before anyone reconciled it, gets chased
 * again every seven days until a person happens to notice - which is how an
 * automated reminder turns into an annoyance and then into a complaint.
 *
 * This is a claim, not a payment: it stops the chasing and asks somebody to
 * check, rather than marking the invoice paid on the customer's say-so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('payment_claimed_at')->nullable()->after('amount_paid');
            $table->string('payment_claim_note', 500)->nullable()->after('payment_claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_claimed_at', 'payment_claim_note']);
        });
    }
};
