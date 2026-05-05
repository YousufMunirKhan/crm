<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_calling_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('international_phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('email_source', 32)->nullable(); // enrichment, manual
            $table->string('website', 2048)->nullable();
            $table->text('formatted_address')->nullable();
            $table->string('postcode_extracted', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('types')->nullable();
            $table->string('business_status', 32)->nullable();
            $table->string('google_maps_uri', 2048)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('user_rating_count')->nullable();
            $table->string('price_level', 48)->nullable();
            $table->text('editorial_summary')->nullable();
            $table->json('opening_hours_summary')->nullable();
            $table->json('extra_payload')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 32)->default('google_places');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('email');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_calling_contacts');
    }
};
