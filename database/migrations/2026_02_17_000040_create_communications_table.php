<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('channel', ['whatsapp', 'email', 'sms']);
            $table->enum('direction', ['inbound', 'outbound']);
            $table->text('message');
            $table->string('status')->default('pending');
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['channel', 'direction']);
            $table->index(['customer_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};


