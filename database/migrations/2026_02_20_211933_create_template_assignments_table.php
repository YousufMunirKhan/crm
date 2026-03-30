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
        Schema::create('template_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('function_type'); // appointment, invoice, welcome, follow_up, etc.
            $table->string('template_type'); // email, sms, whatsapp
            $table->unsignedBigInteger('template_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['function_type', 'template_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_assignments');
    }
};
