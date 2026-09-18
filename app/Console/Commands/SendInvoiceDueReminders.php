<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\InvoiceService;
use App\Services\AutomatedEmailSender;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailables\Attachment;

/**
 * A word before an invoice falls due, rather than only after.
 *
 * Same shape as the chase that follows it, and deliberately not the same tone:
 * this one assumes payment is simply not out yet.
 */
class SendInvoiceDueReminders extends Command
{
    protected $signature = 'emails:invoice-due
        {--days=3 : How many days before the due date}
        {--dry-run}
        {--preview= : Send one example to this address, built from a real invoice}';

    protected $description = 'Email customers whose invoice falls due shortly';

    public function handle(AutomatedEmailSender $sender, InvoiceService $invoices): int
    {
        $days = max(1, (int) $this->option('days'));
        $on = now(config('app.display_timezone'))->addDays($days)->toDateString();

        $due = Invoice::query()
            ->whereIn('status', ['sent', 'partially_paid'])
            ->whereDate('due_date', $on)
            ->whereColumn('amount_paid', '<', 'total')
            ->with('customer')
            ->get();

        // A real invoice, so the preview carries a real PDF and real figures.
        if ($preview = $this->option('preview')) {
            $latest = Invoice::with('customer')->whereHas('customer')->latest('id')->first();

            if (! $latest) {
                $this->warn('No invoice to build an example from.');

                return self::SUCCESS;
            }

            $due = collect([$latest]);
            $overdue = $due;
        }

        $sent = 0;

        foreach ($due as $invoice) {
            $customer = $invoice->customer;
            $email = trim((string) ($customer->email ?? ''));
            $previewTo = $this->option('preview');

            if ($email === '' && ! $previewTo) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('%s  %s  <%s>', $invoice->invoice_number, $this->money($invoice), $email));

                continue;
            }

            $mail = new AutomatedEmail(
                mailSubject: 'Invoice '.$invoice->invoice_number.' is due on '.$invoice->due_date->format('j M'),
                mailView: 'emails.automated.invoice-due',
                mailData: [
                    'companyName' => $sender->companyName(),
                    'customerName' => $customer->name ?: ($customer->business_name ?: 'there'),
                    'invoiceNumber' => $invoice->invoice_number,
                    'dueDate' => $invoice->due_date->format('l j F Y'),
                    'amountDue' => $this->money($invoice),
                    'daysUntilDue' => $days,
                ],
                mailFiles: $this->pdf($invoice, $invoices),
            );

            if ($previewTo) {
                $mail->mailSubject = '[Preview] '.$mail->mailSubject;
                $sender->send('preview:invoice-due:'.now()->format('YmdHis'), $previewTo, $mail);
                $this->info("Sent a due-soon example to {$previewTo}.");

                return self::SUCCESS;
            }

            if ($sender->send('invoice-due:'.$invoice->id, $email, $mail, ['customer_id' => $customer->id])) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? $due->count().' invoice(s) fall due on '.$on.'.'
            : "Sent {$sent} due-soon reminder(s).");

        return self::SUCCESS;
    }

    private function money(Invoice $invoice): string
    {
        $outstanding = (float) $invoice->total - (float) $invoice->amount_paid;

        return ($invoice->currency ?: 'GBP').' '.number_format(max(0, $outstanding), 2);
    }

    /** A dead link helps nobody, and there is no customer-facing invoice page. */
    private function pdf(Invoice $invoice, InvoiceService $invoices): array
    {
        try {
            $pdf = $invoices->generatePDF($invoice);

            return [
                Attachment::fromData(fn () => $pdf->output(), $invoices->pdfFileName($invoice))
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            // Worth sending without it rather than not sending.
            $this->warn('No PDF for '.$invoice->invoice_number.': '.$e->getMessage());

            return [];
        }
    }
}
