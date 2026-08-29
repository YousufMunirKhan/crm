<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs text that was written as UTF-8, read back as Windows-1252, and
 * re-encoded as UTF-8 - so an en dash became "a EUR quote" and a pound sign
 * became "A pound".
 *
 * The fix is the exact inverse: encode the characters back to Windows-1252
 * bytes, then reinterpret those bytes as UTF-8.
 *
 * Dry run by default. This rewrites real customer data.
 */
class RepairMojibakeText extends Command
{
    protected $signature = 'data:repair-mojibake
        {--confirm : Actually write the repaired values}
        {--limit=20 : How many samples to show in a dry run}';

    protected $description = 'Repair double-encoded (mojibake) text in customer and ticket data';

    /** Byte sequences that only appear in mis-decoded text. */
    private const MARKERS = ['â€', 'Â£', 'Â ', 'Ã©', 'Ã¨', 'Ã¼', 'Ã¶', 'Ã¤', 'Ã±'];

    /** @var array<string, list<string>> */
    private const TARGETS = [
        'customers' => ['name', 'business_name', 'address', 'notes', 'city'],
        'tickets' => ['subject', 'description'],
        'leads' => ['lost_reason'],
        'products' => ['name', 'description'],
        'email_templates' => ['name', 'subject'],
        'message_templates' => ['name', 'message'],
    ];

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        $samples = [];
        $repaired = 0;
        $scanned = 0;

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $query = DB::table($table)
                    ->select('id', $column)
                    ->where(function ($w) use ($column) {
                        foreach (self::MARKERS as $marker) {
                            $w->orWhere($column, 'like', '%'.$marker.'%');
                        }
                    });

                foreach ($query->cursor() as $row) {
                    $scanned++;
                    $original = (string) $row->{$column};
                    $fixed = $this->repair($original);

                    if ($fixed === null || $fixed === $original) {
                        continue;
                    }

                    if (count($samples) < (int) $this->option('limit')) {
                        $samples[] = [
                            $table.'.'.$column.' #'.$row->id,
                            mb_strimwidth($original, 0, 44, '…'),
                            mb_strimwidth($fixed, 0, 44, '…'),
                        ];
                    }

                    if ($confirm) {
                        DB::table($table)->where('id', $row->id)->update([$column => $fixed]);
                    }

                    $repaired++;
                }
            }
        }

        if ($samples !== []) {
            $this->table(['Field', 'Before', 'After'], $samples);
        }

        if (! $confirm) {
            $this->warn("Dry run: {$repaired} value(s) of {$scanned} scanned would be repaired.");
            $this->line('Re-run with --confirm to write the changes. Back up first.');

            return self::SUCCESS;
        }

        $this->info("Repaired {$repaired} value(s).");

        return self::SUCCESS;
    }

    /**
     * Returns the repaired string, or null when it cannot be repaired safely.
     */
    private function repair(string $value): ?string
    {
        $current = $value;

        // Some values were mangled more than once.
        for ($pass = 0; $pass < 3; $pass++) {
            if (! $this->looksMangled($current)) {
                break;
            }

            $candidate = @mb_convert_encoding($current, 'Windows-1252', 'UTF-8');

            // If the round trip is lossy or produces invalid UTF-8, leave the
            // value alone rather than risk making it worse.
            if ($candidate === false || $candidate === '' || ! mb_check_encoding($candidate, 'UTF-8')) {
                return $pass > 0 ? $current : null;
            }

            $current = $candidate;
        }

        return $current === $value ? null : $current;
    }

    private function looksMangled(string $value): bool
    {
        foreach (self::MARKERS as $marker) {
            if (str_contains($value, $marker)) {
                return true;
            }
        }

        return false;
    }
}
