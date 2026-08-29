<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * commission_sales.credited_user_id and assigned_by_user_id cascaded on delete,
 * so removing a user erased every commission ever credited to them. Commission
 * history is a payroll record: keep the row and null the reference instead.
 */
return new class extends Migration
{
    /** @var array<string, string> column => foreign key name */
    private array $columns = [
        'credited_user_id' => 'commission_sales_credited_user_id_foreign',
        'assigned_by_user_id' => 'commission_sales_assigned_by_user_id_foreign',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('commission_sales') || ! $this->isMySql()) {
            return;
        }

        foreach ($this->columns as $column => $fk) {
            if (! Schema::hasColumn('commission_sales', $column)) {
                continue;
            }

            $this->dropForeignIfExists($fk);

            DB::statement("ALTER TABLE `commission_sales` MODIFY `{$column}` BIGINT UNSIGNED NULL");
            DB::statement(
                "ALTER TABLE `commission_sales` ADD CONSTRAINT `{$fk}` ".
                "FOREIGN KEY (`{$column}`) REFERENCES `users` (`id`) ON DELETE SET NULL"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('commission_sales') || ! $this->isMySql()) {
            return;
        }

        foreach ($this->columns as $column => $fk) {
            if (! Schema::hasColumn('commission_sales', $column)) {
                continue;
            }

            $this->dropForeignIfExists($fk);

            // Rows orphaned while the constraint was nullable cannot be made
            // NOT NULL again, so leave the column nullable and restore cascade.
            DB::statement(
                "ALTER TABLE `commission_sales` ADD CONSTRAINT `{$fk}` ".
                "FOREIGN KEY (`{$column}`) REFERENCES `users` (`id`) ON DELETE CASCADE"
            );
        }
    }

    /**
     * Foreign-key surgery below is MySQL syntax. Sqlite (used by the test
     * suite) neither supports it nor needs it, so the migration is a no-op
     * there rather than a hard failure.
     */
    private function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function dropForeignIfExists(string $fk): void
    {
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS '.
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['commission_sales', $fk]
        );

        if ($exists) {
            DB::statement("ALTER TABLE `commission_sales` DROP FOREIGN KEY `{$fk}`");
        }
    }
};
