<?php

namespace App\Console\Commands;

use App\Modules\Invoice\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Moves unpaid invoices past their due date into the "overdue" status.
 *
 * Nothing ever set this status: it was a valid value in the enum, accepted by
 * validation and rendered by a badge in the UI, but unreachable - which made
 * accounts-receivable ageing impossible to report on.
 */
class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue {--dry-run : List what would change without writing}';

    protected $description = 'Flag unpaid invoices past their due date as overdue';

    public function handle(): int
    {
        $query = Invoice::query()
            ->whereIn('status', ['sent', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            // Anything fully settled is not overdue regardless of status.
            ->whereColumn('amount_paid', '<', 'total');

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No invoices to flag.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Invoice', 'Due', 'Total', 'Paid'],
                (clone $query)->limit(50)->get()
                    ->map(fn (Invoice $i) => [
                        $i->invoice_number,
                        optional($i->due_date)->format('Y-m-d'),
                        $i->total,
                        $i->amount_paid,
                    ])->all()
            );
            $this->warn("Dry run: {$count} invoice(s) would be marked overdue.");

            return self::SUCCESS;
        }

        // Chunked and re-checked per row so a payment landing mid-run is not
        // clobbered by a blanket update.
        $updated = 0;
        $query->chunkById(200, function ($invoices) use (&$updated) {
            foreach ($invoices as $invoice) {
                if ((float) $invoice->amount_paid >= (float) $invoice->total) {
                    continue;
                }

                $invoice->update(['status' => 'overdue']);
                $updated++;
            }
        });

        $this->info("Marked {$updated} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
