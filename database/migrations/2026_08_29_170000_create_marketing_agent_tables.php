<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the marketing agent: a weekly plan of who to contact and why,
 * reviewed by a person before anything is sent.
 *
 * The existing campaigns / sent_communications machinery does the sending. A
 * campaign is one message to many people; a plan is a different decision per
 * person, each with its own reason, so it needs its own shape. On approval the
 * items are grouped back into campaigns and handed to the dispatcher that
 * already works.
 *
 * `purpose` is added to the template tables rather than reusing `category`,
 * because the twenty templates already there are for manual sending and must
 * keep working untouched. A NULL purpose means "not part of the agent's set".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['email_templates', 'message_templates'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'purpose')) {
                Schema::table($table, function (Blueprint $t) {
                    // Stable key the planner maps to - names get edited, keys
                    // must not, or a rename silently unhooks a template.
                    $t->string('purpose')->nullable()->after('category')->index();
                });
            }
        }

        Schema::create('marketing_plans', function (Blueprint $table) {
            $table->id();
            $table->date('week_starting');
            $table->string('status')->default('draft')->index();
            $table->string('model')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            /** What the rails dropped and why, so the numbers can be explained. */
            $table->json('rail_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['week_starting', 'deleted_at']);
        });

        Schema::create('marketing_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->string('channel');            // email | sms | whatsapp
            $table->string('purpose');            // maps to a template's purpose
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();

            /** The planner's own words for why this person, this week. */
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('priority')->default(3);

            $table->string('status')->default('pending')->index();
            /** Set when a rail refused it - consent, frequency cap, suppression. */
            $table->string('blocked_reason')->nullable();

            /**
             * Editing "the text" means two different things: this one message,
             * or the template everyone gets. These columns are the first one -
             * an override that touches nobody else.
             */
            $table->string('subject_override')->nullable();
            $table->longText('body_override')->nullable();

            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('sent_communication_id')->nullable()->constrained('sent_communications')->nullOnDelete();
            $table->timestamps();

            // One message per person per plan; the planner should not queue the
            // same customer twice in a week.
            $table->unique(['marketing_plan_id', 'customer_id']);
            $table->index(['marketing_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_plan_items');
        Schema::dropIfExists('marketing_plans');

        foreach (['email_templates', 'message_templates'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'purpose')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('purpose');
                });
            }
        }
    }
};
