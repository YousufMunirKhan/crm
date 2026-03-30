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
        // Table already exists from previous migration, so we'll add new columns
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_templates', 'meta_template_id')) {
                $table->string('meta_template_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'language')) {
                $table->string('language')->default('en_US')->after('category');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'components_json')) {
                $table->json('components_json')->nullable()->after('language');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'status')) {
                $table->string('status')->default('PENDING')->after('components_json');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
        });

        // Add indexes if they don't exist (checking by trying to add and catching error)
        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->unique('name', 'whatsapp_templates_name_unique');
            });
        } catch (\Exception $e) {
            // Index already exists, ignore
        }

        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->index('status', 'whatsapp_templates_status_index');
            });
        } catch (\Exception $e) {
            // Index already exists, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added columns
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_templates', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('whatsapp_templates', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('whatsapp_templates', 'components_json')) {
                $table->dropColumn('components_json');
            }
            if (Schema::hasColumn('whatsapp_templates', 'language')) {
                $table->dropColumn('language');
            }
            if (Schema::hasColumn('whatsapp_templates', 'meta_template_id')) {
                $table->dropColumn('meta_template_id');
            }
        });

        // Drop indexes if they exist
        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->dropUnique('whatsapp_templates_name_unique');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, ignore
        }

        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                $table->dropIndex('whatsapp_templates_status_index');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, ignore
        }
    }
};
