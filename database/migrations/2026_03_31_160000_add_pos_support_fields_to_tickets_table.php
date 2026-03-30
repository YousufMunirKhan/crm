<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('source', 32)->default('crm')->after('ticket_number');
            $table->string('pos_external_id', 64)->nullable()->after('source');
            $table->string('pos_shop_name')->nullable()->after('pos_external_id');
            $table->string('pos_telephone', 64)->nullable();
            $table->string('pos_address', 512)->nullable();
            $table->string('pos_computer_name', 128)->nullable();
            $table->string('pos_support_status', 32)->default('pending');
            $table->text('pos_resolution_notes')->nullable();
            $table->timestamp('pos_submitted_at')->nullable();
            $table->timestamp('pos_sent_at')->nullable();

            $table->index(['source', 'pos_support_status']);
            $table->unique(['source', 'pos_external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['source', 'pos_external_id']);
            $table->dropIndex(['source', 'pos_support_status']);
            $table->dropColumn([
                'source',
                'pos_external_id',
                'pos_shop_name',
                'pos_telephone',
                'pos_address',
                'pos_computer_name',
                'pos_support_status',
                'pos_resolution_notes',
                'pos_submitted_at',
                'pos_sent_at',
            ]);
        });
    }
};
