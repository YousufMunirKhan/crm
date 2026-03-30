<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_list_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_list_id')->constrained('email_lists')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['email_list_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_list_recipients');
    }
};
