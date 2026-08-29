<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Product categories were a free-text column on products, so a category could
 * not be renamed without touching every row, and one typo ("ePos") silently
 * became a new category.
 *
 * The table is seeded from whatever is actually in `products` rather than a
 * hardcoded list - live data is the only honest source of what the categories
 * are, and this has to run against a database that already has products in it.
 */
return new class extends Migration
{
    /** Two names for the same thing, folded on the way in. */
    private const MERGE_INTO_UNCATEGORIZED = ['general', 'uncategorised', 'uncategorized', 'none', 'n/a'];

    private const FALLBACK = 'Uncategorized';

    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Manual ordering, because alphabetical is rarely the order a
            // salesperson wants to see products in.
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'category')) {
            $this->seedFallbackOnly();

            return;
        }

        $existing = DB::table('products')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $seen = [];
        $order = 0;

        foreach ($existing as $raw) {
            $name = $this->canonicalName($raw);
            $slug = Str::slug($name);

            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $isFallback = $name === self::FALLBACK;

            DB::table('product_categories')->insert([
                'name' => $name,
                'slug' => $slug,
                // "Uncategorized" is the leftovers bucket, so it sorts last
                // wherever it appears rather than in the middle of the real
                // categories.
                'sort_order' => $isFallback ? 9999 : $order += 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Products created from an invoice fall back to this, so it must exist
        // even when no product currently carries it.
        if (! isset($seen[Str::slug(self::FALLBACK)])) {
            DB::table('product_categories')->insert([
                'name' => self::FALLBACK,
                'slug' => Str::slug(self::FALLBACK),
                'sort_order' => 9999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedFallbackOnly(): void
    {
        DB::table('product_categories')->insert([
            'name' => self::FALLBACK,
            'slug' => Str::slug(self::FALLBACK),
            'sort_order' => 9999,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Trims, and folds the synonyms of "no category" into one name. Anything
     * else keeps the spelling and casing it already had - "ePOS" is written
     * that way on purpose and must not be title-cased into "Epos".
     */
    private function canonicalName(string $raw): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $raw));

        if (in_array(mb_strtolower($name), self::MERGE_INTO_UNCATEGORIZED, true)) {
            return self::FALLBACK;
        }

        return $name;
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
