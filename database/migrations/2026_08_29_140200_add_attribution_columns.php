<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing attribution.
 *
 * Nothing anywhere recorded where a lead came from beyond a free-text `source`
 * string, so paid spend could never be tied to revenue. These are the fields
 * every ad platform hands back on the landing page.
 */
return new class extends Migration
{
    private array $columns = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'referrer', 'landing_page', 'gclid', 'fbclid',
    ];

    public function up(): void
    {
        foreach (['leads', 'customers'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach ($this->columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    // referrer and landing_page can be long; the rest are short.
                    in_array($column, ['referrer', 'landing_page'], true)
                        ? $blueprint->text($column)->nullable()
                        : $blueprint->string($column, 191)->nullable();
                }
            });
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index(['utm_source', 'utm_campaign'], 'leads_utm_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex('leads_utm_index');
            });
        }

        foreach (['leads', 'customers'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach ($this->columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
