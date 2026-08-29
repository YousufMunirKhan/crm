<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the two breaks that made product revenue and campaign attribution
 * impossible to compute:
 *
 *   invoice_items stored a free-text description with no product_id, so
 *   invoice revenue could never be attributed to a product - even though the
 *   invoice screen autocompletes against the catalogue and can create rows in
 *   it, then discards the id.
 *
 *   invoices had only customer_id - no lead_id - so a won deal and the invoice
 *   raised for it had nothing joining them. That is also why executive revenue
 *   double-counts: won lead_items and invoices are summed with no dedupe key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('invoice_id')
                    ->constrained('products')->nullOnDelete();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->after('customer_id')
                    ->constrained('leads')->nullOnDelete();
            }
        });

        $this->backfillProductIds();
    }

    /**
     * Best-effort backfill by exact, case-insensitive name match.
     *
     * Only unambiguous matches are linked: a description matching two catalogue
     * products is left null rather than guessed at, because this feeds revenue
     * and commission attribution.
     */
    private function backfillProductIds(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $products = DB::table('products')
            ->select('id', 'name')
            ->get()
            ->groupBy(fn ($p) => mb_strtolower(trim($p->name)));

        $linked = 0;
        $ambiguous = 0;

        DB::table('invoice_items')->whereNull('product_id')->orderBy('id')->chunkById(500, function ($items) use ($products, &$linked, &$ambiguous) {
            foreach ($items as $item) {
                $key = mb_strtolower(trim((string) $item->description));
                $matches = $products->get($key);

                if (! $matches || $matches->count() !== 1) {
                    if ($matches && $matches->count() > 1) {
                        $ambiguous++;
                    }

                    continue;
                }

                DB::table('invoice_items')
                    ->where('id', $item->id)
                    ->update(['product_id' => $matches->first()->id]);

                $linked++;
            }
        });

        // Surfaced rather than silent: an unlinked row is one whose revenue
        // still cannot be attributed to a product.
        if (function_exists('logger')) {
            logger()->info('invoice_items product backfill', [
                'linked' => $linked,
                'ambiguous_skipped' => $ambiguous,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'lead_id')) {
                $table->dropConstrainedForeignId('lead_id');
            }
        });
    }
};
