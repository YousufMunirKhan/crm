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
        Schema::create('whatsapp_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id');
            $table->enum('direction', ['OUTBOUND', 'INBOUND']);
            $table->text('endpoint');
            $table->string('method');
            $table->integer('status_code')->nullable();
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('correlation_id');
            $table->index('direction');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_api_logs');
    }
};
