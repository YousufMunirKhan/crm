<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('method', 64)->nullable();
            $table->string('reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'payment_date']);
        });

        $paidInvoices = DB::table('invoices')
            ->where('amount_paid', '>', 0)
            ->select(['id', 'created_by', 'invoice_date', 'amount_paid', 'created_at', 'updated_at'])
            ->get();

        foreach ($paidInvoices as $invoice) {
            DB::table('invoice_payments')->insert([
                'invoice_id' => $invoice->id,
                'received_by_user_id' => $invoice->created_by,
                'payment_date' => $invoice->invoice_date,
                'amount' => $invoice->amount_paid,
                'method' => null,
                'reference' => 'Opening balance from existing invoice amount paid',
                'notes' => null,
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
