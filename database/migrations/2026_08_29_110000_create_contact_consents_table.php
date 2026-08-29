<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Channel-agnostic consent and suppression record.
 *
 * Replaces email_unsubscribes, which was a suppression list keyed on an email
 * address and so could not represent an SMS or WhatsApp opt-out at all. PECR
 * reg. 22 requires evidence of consent (or a valid soft opt-in) per recipient
 * per channel, which needs an opt-in timestamp and a source, not just a
 * did-they-complain flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_consents', function (Blueprint $table) {
            $table->id();

            // Normalised email address or E.164-ish phone number.
            $table->string('identifier', 191);
            $table->string('channel', 16); // email | sms | whatsapp

            $table->string('status', 16)->default('unknown'); // opt_in | opt_out | unknown

            $table->timestamp('opt_in_at')->nullable();
            $table->timestamp('opt_out_at')->nullable();

            // How consent was obtained, or how the opt-out arrived.
            $table->string('source', 64)->nullable();
            // Lawful basis relied on: consent | soft_opt_in | legitimate_interest
            $table->string('lawful_basis', 32)->nullable();
            // Free-form evidence: form URL, import filename, inbound message id.
            $table->text('evidence')->nullable();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->timestamps();

            $table->unique(['identifier', 'channel']);
            $table->index(['channel', 'status']);
        });

        // Carry the existing email suppression list across so nobody who has
        // already opted out starts receiving mail again.
        if (Schema::hasTable('email_unsubscribes')) {
            DB::table('email_unsubscribes')->orderBy('id')->chunk(500, function ($rows) {
                $now = now();
                $payload = [];

                foreach ($rows as $row) {
                    $email = strtolower(trim((string) $row->email));
                    if ($email === '') {
                        continue;
                    }

                    $payload[] = [
                        'identifier' => $email,
                        'channel' => 'email',
                        'status' => 'opt_out',
                        'opt_in_at' => null,
                        'opt_out_at' => $row->unsubscribed_at ?? $row->created_at ?? $now,
                        'source' => 'migrated_from_email_unsubscribes',
                        'lawful_basis' => null,
                        'evidence' => null,
                        'customer_id' => null,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if ($payload !== []) {
                    DB::table('contact_consents')->insertOrIgnore($payload);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_consents');
    }
};
