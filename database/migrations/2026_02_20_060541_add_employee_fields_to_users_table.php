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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('employee_type', ['field_worker', 'call_center', 'ticket_manager'])->nullable()->after('role_id');
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->date('hire_date')->nullable()->after('address');
            $table->timestamp('contract_sent_at')->nullable()->after('hire_date');
            $table->string('contract_pdf_path')->nullable()->after('contract_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_type',
                'phone',
                'address',
                'hire_date',
                'contract_sent_at',
                'contract_pdf_path',
            ]);
        });
    }
};
