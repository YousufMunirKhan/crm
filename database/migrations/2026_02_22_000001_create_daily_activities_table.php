<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('activity_date');
            $table->text('description');
            $table->timestamps();

            $table->index(['user_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activities');
    }
};
