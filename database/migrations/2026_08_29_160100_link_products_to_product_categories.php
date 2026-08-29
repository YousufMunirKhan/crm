<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Points every product at its category row.
 *
 * The `category` string stays. Seven places still read it - the model, the
 * API controller, three Filament classes, the products screen and the invoice
 * builder - and on a live system a big-bang switch means one of them is missed
 * and fails quietly. The string is kept in sync by the Product model until
 * every reader is on category_id, then a later migration can drop it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('category')
                    ->constrained('product_categories')
                    // A category with products cannot be hard-deleted out from
                    // under them; the model soft-deletes instead.
                    ->nullOnDelete();
            });
        }

        $categories = DB::table('product_categories')->pluck('id', 'slug');

        DB::table('products')->orderBy('id')->chunkById(200, function ($products) use ($categories) {
            foreach ($products as $product) {
                $slug = Str::slug(trim((string) ($product->category ?? '')));
                $id = $categories[$slug] ?? $categories[Str::slug('Uncategorized')] ?? null;

                if ($id === null) {
                    continue;
                }

                // "General" merged into "Uncategorized", so the string is
                // rewritten to match the row it now points at - otherwise the
                // two would disagree from day one.
                $name = DB::table('product_categories')->where('id', $id)->value('name');

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['category_id' => $id, 'category' => $name]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }
    }
};
