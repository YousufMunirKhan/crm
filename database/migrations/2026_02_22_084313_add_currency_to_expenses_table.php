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
        // Currency column is now included in create_expenses_table migration
        // This migration is kept for backward compatibility but does nothing
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'currency')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('currency', 3)->default('GBP')->after('amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
