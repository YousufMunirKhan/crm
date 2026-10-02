<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A run was always "one postcode through Google Places". Sweeps around the
 * places we already have customers go through a different engine - the Maps
 * scraper on an office PC - and are keyed on an area (a postcode district, or
 * a town when nobody recorded a postcode), not a single postcode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cold_calling_runs')) {
            return;
        }

        Schema::table('cold_calling_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('cold_calling_runs', 'engine')) {
                // google_places | gmaps_scraper
                $table->string('engine', 24)->default('google_places')->index();
            }
            if (! Schema::hasColumn('cold_calling_runs', 'area_key')) {
                $table->string('area_key', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cold_calling_runs')) {
            return;
        }

        Schema::table('cold_calling_runs', function (Blueprint $table) {
            foreach (['engine', 'area_key'] as $column) {
                if (Schema::hasColumn('cold_calling_runs', $column)) {
                    $table->dropIndex([$column]);
                    $table->dropColumn($column);
                }
            }
        });
    }
};
