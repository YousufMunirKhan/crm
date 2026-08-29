<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign identity.
 *
 * sent_communications had no campaign or batch key, so "how did the March push
 * perform?" was unanswerable - the only report available was a date range
 * across every message ever sent, of every kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 16);                 // email | sms | whatsapp
            $table->string('status', 16)->default('draft'); // draft|scheduled|sending|sent|cancelled|failed

            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_type', 32)->nullable();

            // The audience filter this campaign was built from, so a send can
            // be reproduced or reviewed after the fact.
            $table->json('audience_filters')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel', 'status']);
            $table->index('scheduled_at');
        });

        if (Schema::hasTable('sent_communications') && ! Schema::hasColumn('sent_communications', 'campaign_id')) {
            Schema::table('sent_communications', function (Blueprint $table) {
                $table->foreignId('campaign_id')->nullable()->after('id')
                    ->constrained('campaigns')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sent_communications') && Schema::hasColumn('sent_communications', 'campaign_id')) {
            Schema::table('sent_communications', function (Blueprint $table) {
                $table->dropConstrainedForeignId('campaign_id');
            });
        }

        Schema::dropIfExists('campaigns');
    }
};
