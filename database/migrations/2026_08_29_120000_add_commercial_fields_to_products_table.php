<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the product catalogue the fields a catalogue needs.
 *
 * products was name/description/category/is_active only, so every price in the
 * business was retyped by hand on each quote and invoice, and margin was not
 * computable at all because no cost was recorded anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sku')) {
                $table->string('sku', 64)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('products', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'cost_price')) {
                // Nullable and never exposed to non-managers; margin needs it.
                $table->decimal('cost_price', 12, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('products', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('cost_price');
            }
            if (! Schema::hasColumn('products', 'currency')) {
                $table->string('currency', 3)->default('GBP')->after('tax_rate');
            }
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 32)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path')->nullable()->after('unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['sku', 'unit_price', 'cost_price', 'tax_rate', 'currency', 'unit', 'image_path'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
