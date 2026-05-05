<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_communications', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('error_message');
            $table->unsignedInteger('open_count')->default(0)->after('opened_at');
            $table->string('failure_category', 32)->nullable()->after('open_count');
        });
    }

    public function down(): void
    {
        Schema::table('sent_communications', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'open_count', 'failure_category']);
        });
    }
};
