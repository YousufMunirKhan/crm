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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('general'); // welcome, epos, teya, appointment, invoice, follow_up, quote, thank_you, reminder, custom
            $table->string('subject');
            $table->text('description')->nullable();
            $table->longText('content'); // JSON structure with sections/blocks
            $table->json('variables')->nullable(); // Available variables like {{customer_name}}, {{appointment_date}}
            $table->boolean('is_active')->default(true);
            $table->boolean('is_prebuilt')->default(false); // Pre-built templates cannot be deleted
            $table->string('preview_image')->nullable(); // Screenshot/preview of template
            $table->integer('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
