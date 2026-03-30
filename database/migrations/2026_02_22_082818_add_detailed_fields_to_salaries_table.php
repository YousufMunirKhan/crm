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
        Schema::table('salaries', function (Blueprint $table) {
            $table->json('bonuses')->nullable()->after('allowances'); // e.g., [{"name": "Performance Bonus", "amount": 500}]
            $table->json('deductions_detail')->nullable()->after('deductions'); // e.g., [{"name": "Tax", "amount": 200}]
            $table->integer('attendance_days')->nullable()->after('net_salary');
            $table->text('notes')->nullable()->after('attendance_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn(['bonuses', 'deductions_detail', 'attendance_days', 'notes']);
        });
    }
};
