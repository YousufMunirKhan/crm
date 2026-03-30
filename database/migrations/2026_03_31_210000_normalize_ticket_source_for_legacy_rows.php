<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Idempotent safety: list filters use source. Fix any inconsistent legacy rows.
     */
    public function up(): void
    {
        $missingSource = function ($q) {
            $q->whereNull('source')->orWhere('source', '');
        };

        DB::table('tickets')
            ->where($missingSource)
            ->whereNotNull('pos_external_id')
            ->where('pos_external_id', '!=', '')
            ->update(['source' => 'pos_support']);

        DB::table('tickets')
            ->where($missingSource)
            ->where(function ($q) {
                $q->whereNull('pos_external_id')->orWhere('pos_external_id', '');
            })
            ->update(['source' => 'crm']);
    }

    public function down(): void
    {
        // No reliable rollback; data fix only.
    }
};
