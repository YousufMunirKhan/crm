<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved audiences.
 *
 * Audience filters were request-only: every send rebuilt them by hand, they
 * could not be reused or reviewed, and there was no record of who a past
 * campaign actually went to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // The same filter shape the management screens already post.
            $table->json('filters');

            $table->boolean('is_shared')->default(true);
            $table->unsignedInteger('last_count')->nullable();
            $table->timestamp('last_counted_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_shared');
        });

        if (Schema::hasTable('campaigns') && ! Schema::hasColumn('campaigns', 'audience_segment_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->foreignId('audience_segment_id')->nullable()->after('audience_filters')
                    ->constrained('audience_segments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'audience_segment_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropConstrainedForeignId('audience_segment_id');
            });
        }

        Schema::dropIfExists('audience_segments');
    }
};
