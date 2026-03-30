<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_lists', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->after('original_file_name');
            $table->foreign('template_id')->references('id')->on('email_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_lists', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
