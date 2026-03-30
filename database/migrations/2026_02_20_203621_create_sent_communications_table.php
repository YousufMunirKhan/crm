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
        Schema::create('sent_communications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // email, sms, whatsapp
            $table->string('template_type')->nullable(); // email_template, message_template, whatsapp_template
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('content');
            $table->string('status')->default('sent'); // sent, failed, pending
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'type']);
            $table->index(['lead_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sent_communications');
    }
};
