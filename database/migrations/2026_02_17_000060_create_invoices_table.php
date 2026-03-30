<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(20.00);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('currency', ['GBP'])->default('GBP');
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'overdue'])->default('draft');
            $table->timestamps();

            $table->index(['customer_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};


