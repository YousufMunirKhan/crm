<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Models\SentCommunication;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\InvoiceService;
use App\Services\AutomatedEmailSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\URL;

/**
 * Chases an overdue invoice, then keeps chasing weekly.
 *
 * The interval is measured from the last chase that actually went, not from the
 * due date, so a week of failed sends does not silently turn into a fortnight's
 * gap - and a rerun on the same day cannot chase twice.
 */
class SendInvoiceOverdueChases extends Command
{
    protected $signature = 'emails:invoice-overdue
        {--every=7 : Days between chases}
        {--dry-run}
        {--preview= : Send one example to this address, built from a real invoice}';

    protected $description = 'Email customers whose invoice is past its due date';

    public function handle(AutomatedEmailSender $sender, InvoiceService $invoices): int
    {
        $every = max(1, (int) $this->option('every'));
        $today = now(config('app.display_timezone'));

        $overdue = Invoice::query()
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereColumn('amount_paid', '<', 'total')
            // Chasing somebody who has already said they paid is how a reminder
            // becomes a complaint.
            ->whereNull('payment_claimed_at')
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

        foreach ($overdue as $invoice) {
            $customer = $invoice->customer;
            $email = trim((string) ($customer->email ?? ''));
            $previewTo = $this->option('preview');

            if (! $previewTo && ($email === '' || ! $this->isDueAChase($invoice, $every))) {
                continue;
            }

            $daysOverdue = (int) $invoice->due_date->startOfDay()->diffInDays($today->copy()->startOfDay());

            if ($previewTo) {
                // The newest invoice may not actually be late; the template is
                // what is being looked at, so give it something to show.
                $daysOverdue = max(1, $daysOverdue);
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('%s  %d days  %s  <%s>', $invoice->invoice_number, $daysOverdue, $this->money($invoice), $email));

                continue;
            }

            $mail = new AutomatedEmail(
                mailSubject: 'Overdue: invoice '.$invoice->invoice_number,
                mailView: 'emails.automated.invoice-overdue',
                mailData: [
                    'companyName' => $sender->companyName(),
                    'customerName' => $customer->name ?: ($customer->business_name ?: 'there'),
                    'invoiceNumber' => $invoice->invoice_number,
                    'dueDate' => $invoice->due_date->format('l j F Y'),
                    'amountDue' => $this->money($invoice),
                    'paidUrl' => URL::signedRoute('invoices.payment-claimed', ['invoice' => $invoice->id]),
                    'daysOverdue' => $daysOverdue,
                ],
                mailFiles: $this->pdf($invoice, $invoices),
            );

            if ($previewTo) {
                $mail->mailSubject = '[Preview] '.$mail->mailSubject;
                $sender->send('preview:invoice-overdue:'.now()->format('YmdHis'), $previewTo, $mail);
                $this->info("Sent an overdue chase example to {$previewTo}.");

                return self::SUCCESS;
            }

            $reference = 'invoice-overdue:'.$invoice->id.':'.$today->toDateString();

            if ($sender->send($reference, $email, $mail, ['customer_id' => $customer->id])) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? $overdue->count().' invoice(s) overdue in total.'
            : "Sent {$sent} overdue chase(s).");

        return self::SUCCESS;
    }

    private function isDueAChase(Invoice $invoice, int $every): bool
    {
        $last = SentCommunication::query()
            ->where('reference', 'like', 'invoice-overdue:'.$invoice->id.':%')
            ->where('status', 'sent')
            ->max('sent_at');

        // Compared as two moments rather than with a diff: diffInDays is signed,
        // so against a date in the past it answers -8, and -8 is not >= 7.
        return $last === null || Carbon::parse($last)->addDays($every)->lte(now());
    }

    private function money(Invoice $invoice): string
    {
        $outstanding = (float) $invoice->total - (float) $invoice->amount_paid;

        return ($invoice->currency ?: 'GBP').' '.number_format(max(0, $outstanding), 2);
    }

    private function pdf(Invoice $invoice, InvoiceService $invoices): array
    {
        try {
            $pdf = $invoices->generatePDF($invoice);

            return [
                Attachment::fromData(fn () => $pdf->output(), $invoices->pdfFileName($invoice))
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            $this->warn('No PDF for '.$invoice->invoice_number.': '.$e->getMessage());

            return [];
        }
    }
}
