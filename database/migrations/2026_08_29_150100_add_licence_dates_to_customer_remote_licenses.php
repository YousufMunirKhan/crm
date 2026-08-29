<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Licence expiry dates.
 *
 * lic_days was a free-text VARCHAR, so a licence's expiry could not be
 * computed at all - which is why renewal reminders were impossible rather than
 * merely unbuilt. Backfills from lic_days where it holds a plain number of days.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_remote_licenses')) {
            return;
        }

        Schema::table('customer_remote_licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_remote_licenses', 'starts_at')) {
                $table->date('starts_at')->nullable();
            }
            if (! Schema::hasColumn('customer_remote_licenses', 'expires_at')) {
                $table->date('expires_at')->nullable()->index();
            }
        });

        if (! Schema::hasColumn('customer_remote_licenses', 'lic_days')) {
            return;
        }

        // Only rows whose lic_days is a plain integer can be interpreted; the
        // rest are left null rather than guessed at.
        DB::table('customer_remote_licenses')
            ->whereNull('expires_at')
            ->whereNotNull('lic_days')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $days = trim((string) $row->lic_days);

                    if ($days === '' || ! ctype_digit($days)) {
                        continue;
                    }

                    $start = $row->created_at ? \Carbon\Carbon::parse($row->created_at) : now();

                    DB::table('customer_remote_licenses')
                        ->where('id', $row->id)
                        ->update([
                            'starts_at' => $start->toDateString(),
                            'expires_at' => $start->copy()->addDays((int) $days)->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_remote_licenses')) {
            return;
        }

        Schema::table('customer_remote_licenses', function (Blueprint $table) {
            foreach (['starts_at', 'expires_at'] as $column) {
                if (Schema::hasColumn('customer_remote_licenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
