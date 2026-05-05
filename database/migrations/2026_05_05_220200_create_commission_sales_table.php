<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_item_id')->nullable()->constrained('lead_items')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('credited_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('commission_amount', 12, 2);
            $table->string('commission_currency', 3);
            $table->string('commission_role', 32)->default('single_owner');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'lead_item_id']);
            $table->index(['credited_user_id', 'commission_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_sales');
    }
};
