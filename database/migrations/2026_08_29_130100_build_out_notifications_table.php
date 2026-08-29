<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notifications table was created with only an id and timestamps, while
 * NotificationController queries notifiable_id and read_at - so every
 * notification endpoint failed with "column not found" rather than simply
 * returning an empty list.
 *
 * Columns follow Laravel's standard notification shape so the table can also
 * back database notifications later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->after('id');
            }
            if (! Schema::hasColumn('notifications', 'notifiable_type')) {
                $table->string('notifiable_type')->default(\App\Models\User::class)->after('type');
            }
            if (! Schema::hasColumn('notifications', 'notifiable_id')) {
                $table->unsignedBigInteger('notifiable_id')->after('notifiable_type');
            }
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->nullable()->after('notifiable_id');
            }
            if (! Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->after('title');
            }
            if (! Schema::hasColumn('notifications', 'data')) {
                $table->json('data')->nullable()->after('message');
            }
            if (! Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('data');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_recipient_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_recipient_index');
            foreach (['type', 'notifiable_type', 'notifiable_id', 'title', 'message', 'data', 'read_at'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
