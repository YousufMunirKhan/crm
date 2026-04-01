<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });

        $rows = DB::table('tickets')->whereNotNull('assigned_to')->get(['id', 'assigned_to']);
        foreach ($rows as $row) {
            DB::table('ticket_assignees')->insertOrIgnore([
                'ticket_id' => $row->id,
                'user_id' => $row->assigned_to,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignees');
    }
};
