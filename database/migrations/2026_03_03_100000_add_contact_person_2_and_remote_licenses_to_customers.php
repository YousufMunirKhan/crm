<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_person_2_name')->nullable()->after('owner_name');
            $table->string('contact_person_2_phone')->nullable()->after('contact_person_2_name');
        });

        Schema::create('customer_remote_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('anydesk_rustdesk')->nullable();
            $table->string('passwords')->nullable();
            $table->string('epos_type')->nullable();
            $table->string('lic_days')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Migrate existing single remote/license data to the new table (if old columns exist)
        if (Schema::hasColumn('customers', 'anydesk_rustdesk')) {
            $customers = \DB::table('customers')
                ->where(function ($q) {
                    $q->whereNotNull('anydesk_rustdesk')->where('anydesk_rustdesk', '!=', '')
                        ->orWhereNotNull('passwords')->where('passwords', '!=', '')
                        ->orWhereNotNull('epos_type')->where('epos_type', '!=', '')
                        ->orWhereNotNull('lic_days');
                })
                ->get(['id', 'anydesk_rustdesk', 'passwords', 'epos_type', 'lic_days']);

            foreach ($customers as $c) {
                if ($c->anydesk_rustdesk || $c->passwords || $c->epos_type || $c->lic_days !== null) {
                    \DB::table('customer_remote_licenses')->insert([
                        'customer_id' => $c->id,
                        'anydesk_rustdesk' => $c->anydesk_rustdesk,
                        'passwords' => $c->passwords,
                        'epos_type' => $c->epos_type,
                        'lic_days' => $c->lic_days !== null ? (string) $c->lic_days : null,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_remote_licenses');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['contact_person_2_name', 'contact_person_2_phone']);
        });
    }
};
