<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes for the records that carry financial and commercial history.
 *
 * Deleting a customer previously cascaded through invoices -> invoice_items and
 * invoice_payments, plus leads -> lead_items and commission_sales, in a single
 * unrecoverable statement. UK VAT records must be retained for six years, so
 * these rows are now retired rather than destroyed.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'customers',
        'leads',
        'lead_items',
        'invoices',
        'invoice_items',
        'invoice_payments',
        'commission_sales',
        'products',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
