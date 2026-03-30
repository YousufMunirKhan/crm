<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->decimal('house_allowance', 12, 2)->default(0)->after('allowances');
            $table->decimal('medical_allowance', 12, 2)->default(0)->after('house_allowance');
            $table->decimal('other_allowance', 12, 2)->default(0)->after('medical_allowance');
            $table->decimal('tax', 12, 2)->default(0)->after('deductions');
            $table->decimal('loan_deduction', 12, 2)->default(0)->after('tax');
            $table->decimal('other_deduction', 12, 2)->default(0)->after('loan_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn([
                'house_allowance',
                'medical_allowance',
                'other_allowance',
                'tax',
                'loan_deduction',
                'other_deduction',
            ]);
        });
    }
};
