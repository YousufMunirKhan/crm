<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Http\Request;

/**
 * The customer's way of saying "this is already paid" and being left alone.
 *
 * Reached from a signed link in the chase email, so there is no login and no
 * password to hand a customer. The signature is what proves the link came from
 * us; nothing in the request decides which invoice is touched.
 *
 * It records a claim rather than a payment. Marking an invoice settled because
 * somebody said so would put a hole in the accounts; stopping the chase and
 * asking a person to check costs nothing if they are wrong.
 */
class InvoicePaymentClaimController extends Controller
{
    public function __invoke(Request $request, int $invoice)
    {
        $model = Invoice::with('customer')->find($invoice);

        if (! $model) {
            abort(404);
        }

        // Idempotent: the link lives in an email and email links get clicked twice.
        if (! $model->payment_claimed_at) {
            $model->forceFill([
                'payment_claimed_at' => now(),
                'payment_claim_note' => 'Reported as paid by the customer from the reminder email.',
            ])->save();

            $this->tellSomebody($model);
        }

        return response()->view('invoices.payment-claimed', [
            'invoice' => $model,
            'companyName' => \App\Modules\Settings\Models\Setting::query()
                ->where('key', 'company_name')->value('value') ?: config('app.name'),
        ]);
    }

    /**
     * Somebody has to check it, or the chase stops and nothing replaces it.
     */
    private function tellSomebody(Invoice $invoice): void
    {
        $managers = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['Admin', 'System Admin', 'Manager']))
            ->pluck('id');

        foreach ($managers as $userId) {
            Notification::notifyUser(
                (int) $userId,
                'invoice_payment_claimed',
                'Customer says invoice '.$invoice->invoice_number.' is paid',
                ($invoice->customer?->business_name ?: $invoice->customer?->name ?: 'A customer')
                    .' reported '.$invoice->invoice_number.' as already paid. Reminders have stopped — please check it against the bank.',
                ['invoice_id' => $invoice->id],
            );
        }
    }
}
