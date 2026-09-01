<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One person, one section, granted or taken away - on top of their role.
 *
 * Until now the only way to give somebody a section their role does not have
 * was to fill in `users.nav_permissions`, which *replaces* the role list
 * outright: to add one section you had to reproduce all thirty checkboxes, and
 * from then on that person was frozen on a stale copy that no longer moved when
 * the role changed. Nobody used it. What people did instead is visible in the
 * data - 6 of 15 accounts are Admin, because making somebody an admin was
 * easier than maintaining a private copy of a role.
 *
 * This is deliberately not a second permission system. It is a short list of
 * exceptions to the role, each with a reason and, usually, an end date, so
 * "give Latif invoices until Friday" is one row that expires by itself rather
 * than a permanent change nobody remembers to undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section', 60);

            // grant = they get it even though the role does not give it.
            // revoke = they lose it even though the role does give it.
            $table->string('effect', 10)->default('grant');

            // Null means open-ended. The UI pushes towards a date, because an
            // access change nobody has to renew is how people end up as admins.
            $table->timestamp('expires_at')->nullable();

            // Who did it and why, in their own words. A permission change with
            // no reason attached is unanswerable three months later.
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();

            // Kept rather than deleted, so the history of who had what survives
            // being turned off.
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The lookup every request makes.
            $table->index(['user_id', 'section', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_grants');
    }
};
