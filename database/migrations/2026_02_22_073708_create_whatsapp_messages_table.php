<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->enum('direction', ['IN', 'OUT']);
            $table->string('to_e164')->nullable();
            $table->string('from_e164')->nullable();
            $table->string('type')->default('text'); // text, template, media
            $table->text('body_text')->nullable();
            $table->string('template_name')->nullable();
            $table->json('template_params_json')->nullable();
            $table->string('meta_wamid')->nullable()->unique(); // WhatsApp message ID for idempotency
            $table->string('status')->default('queued'); // queued, sent, delivered, read, failed
            $table->json('meta_payload_json')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('direction');
            $table->index('status');
            $table->index('meta_wamid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
