<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History, failures and an audit trail for the marketing agent.
 *
 * Rebuilding a week deleted the previous plan outright, so there was no way to
 * answer "what did we decide last week and what happened to it". Plans are kept
 * and marked superseded instead.
 *
 * A failed generation left nothing behind at all - the request 500'd and the
 * reason lived only in a log file nobody reads. It is recorded on the plan now.
 *
 * And every decision on a row - approved, skipped, edited, sent, failed - gets
 * an event, because "who cancelled this one and when" is the question asked
 * after something goes out, and a status column cannot answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_plans', function (Blueprint $table) {
            // Set when a rebuild replaces this plan, so the old one is history
            // rather than deleted.
            $table->foreignId('superseded_by_id')->nullable()->after('status')
                ->constrained('marketing_plans')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('superseded_by_id');
            /** Why a generation produced nothing, kept where the screen can show it. */
            $table->text('generation_error')->nullable()->after('notes');
        });

        Schema::create('marketing_plan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_plan_id')->constrained()->cascadeOnDelete();
            // Null for plan-level events: generated, sent, cancelled.
            $table->foreignId('marketing_plan_item_id')->nullable()
                ->constrained('marketing_plan_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action')->index();
            /** Human sentence, written once here so every screen says the same thing. */
            $table->string('summary');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['marketing_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_plan_events');

        Schema::table('marketing_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropColumn(['superseded_at', 'generation_error']);
        });
    }
};
