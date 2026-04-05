<?php

use App\Modules\CRM\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });

        $morph = (new Lead)->getMorphClass();

        DB::table('leads')
            ->whereNull('created_by')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($morph) {
                foreach ($rows as $row) {
                    $userId = DB::table('audit_logs')
                        ->where('auditable_type', $morph)
                        ->where('auditable_id', $row->id)
                        ->where('action', 'created')
                        ->value('user_id');
                    if ($userId) {
                        DB::table('leads')->where('id', $row->id)->update(['created_by' => $userId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
