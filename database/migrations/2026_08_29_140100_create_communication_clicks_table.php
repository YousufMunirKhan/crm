<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Click tracking.
 *
 * Only opens were recorded, and with Apple Mail Privacy Protection inflating
 * open rates those are a weak signal. Without clicks there is no engagement
 * metric, no way to re-target people who acted, and no readout for a test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_communication_id')->constrained('sent_communications')->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 64)->index();
            $table->unsignedInteger('click_count')->default(1);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(['sent_communication_id', 'url_hash'], 'communication_clicks_send_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_clicks');
    }
};
