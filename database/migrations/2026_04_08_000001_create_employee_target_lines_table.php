<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_target_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_target_id')->constrained('employee_targets')->cascadeOnDelete();
            $table->string('line_type', 16); // product | category
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('category_name')->nullable();
            $table->unsignedInteger('target_quantity')->default(0);
            $table->timestamps();

            $table->index(['employee_target_id', 'line_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_target_lines');
    }
};
