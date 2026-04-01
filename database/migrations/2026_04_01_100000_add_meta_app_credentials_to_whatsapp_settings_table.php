<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_settings', 'meta_app_id')) {
                $table->string('meta_app_id')->nullable()->after('phone_number_id');
            }
            if (!Schema::hasColumn('whatsapp_settings', 'meta_app_secret_encrypted')) {
                $table->text('meta_app_secret_encrypted')->nullable()->after('meta_app_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_settings', 'meta_app_secret_encrypted')) {
                $table->dropColumn('meta_app_secret_encrypted');
            }
            if (Schema::hasColumn('whatsapp_settings', 'meta_app_id')) {
                $table->dropColumn('meta_app_id');
            }
        });
    }
};
