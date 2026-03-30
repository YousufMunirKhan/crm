<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->unsignedInteger('target_appointments')->default(0);
            $table->unsignedInteger('target_sales')->default(0); // number of sales (won deals)
            $table->decimal('target_revenue', 12, 2)->default(0); // optional revenue target
            $table->json('meta')->nullable(); // for future extensibility (product-level targets etc.)
            $table->timestamps();

            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_targets');
    }
};

