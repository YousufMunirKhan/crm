<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->string('media_url')->nullable()->after('message');
            $table->string('media_type')->nullable()->after('media_url'); // image, document, video
            // campaign_id will be added in a separate migration after whatsapp_campaigns table is created
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['media_url', 'media_type', 'campaign_id']);
        });
    }
};
