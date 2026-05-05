<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_calling_contact_postcode', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cold_calling_contact_id')->constrained('cold_calling_contacts')->cascadeOnDelete();
            $table->string('postcode_normalized', 16);
            $table->foreignId('cold_calling_run_id')->nullable()->constrained('cold_calling_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cold_calling_contact_id', 'postcode_normalized'], 'cc_contact_postcode_unique');
            $table->index('postcode_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_calling_contact_postcode');
    }
};
