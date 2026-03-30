<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', [
                'follow_up',
                'lead',
                'hot_lead',
                'quotation',
                'won',
                'lost',
            ])->index();
            $table->string('source')->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->decimal('pipeline_value', 12, 2)->default(0);
            $table->string('lost_reason')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};


