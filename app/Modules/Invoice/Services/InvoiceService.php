<?php

namespace App\Modules\Invoice\Services;

use App\Mail\InvoiceEmail;
use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Settings\Models\Setting;
use App\Support\PdfDocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class InvoiceService
{
    public function create(array $data, ?int $userId = null): Invoice
    {
        $customer = Customer::findOrFail($data['customer_id']);

        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $subtotal += $lineTotal;
        }

        $vatRate = $data['vat_rate'] ?? self::defaultVatRate();
        $vatAmount = round($subtotal * $vatRate / 100, 2);
        $total = $subtotal + $vatAmount;

        $invoice = Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'customer_id' => $customer->id,
            'lead_id' => $data['lead_id'] ?? null,
            'created_by' => $userId ?? auth()->id(),
            'invoice_date' => $data['invoice_date'] ?? now(),
            'due_date' => $data['due_date'] ?? now()->addDays(30),
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'amount_paid' => 0,
            'currency' => 'GBP',
            'status' => $data['status'] ?? 'draft',
        ]);

        foreach ($data['items'] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                // Carry the catalogue link through so invoice revenue can be
                // attributed to a product.
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return $invoice->load('items');
    }

    /**
     * Raises an invoice from a won lead, carrying its line items across.
     *
     * A won deal previously had to be re-keyed into an invoice by hand: there
     * was no conversion anywhere, and lead_items / invoice_items were two
     * disconnected line-item systems. Every retype was a chance to mis-price a
     * line and another sale whose revenue could not be attributed.
     *
     * @throws \RuntimeException when the lead has nothing to invoice
     */
    public function createFromLead(\App\Modules\CRM\Models\Lead $lead, array $overrides = [], ?int $userId = null): Invoice
    {
        $lead->loadMissing(['items.product', 'customer']);

        $existing = Invoice::where('lead_id', $lead->id)->first();
        if ($existing) {
            throw new \RuntimeException(
                'This lead has already been invoiced ('.$existing->invoice_number.').'
            );
        }

        // Won items only - a quotation still in play should not be invoiced.
        $items = $lead->items
            ->filter(fn ($item) => $item->status === \App\Modules\CRM\Models\LeadItem::STATUS_WON)
            ->values();

        if ($items->isEmpty()) {
            throw new \RuntimeException('This lead has no won line items to invoice.');
        }

        $payload = [
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'invoice_date' => $overrides['invoice_date'] ?? now(),
            'due_date' => $overrides['due_date'] ?? now()->addDays(30),
            'vat_rate' => $overrides['vat_rate'] ?? self::defaultVatRate(),
            'status' => $overrides['status'] ?? 'draft',
            'items' => $items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->product?->name ?? 'Item',
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->all(),
        ];

        return $this->create($payload, $userId);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $recalc = false;
        $updateData = [];

        if (isset($data['invoice_date'])) {
            $updateData['invoice_date'] = $data['invoice_date'];
        }
        if (isset($data['due_date'])) {
            $updateData['due_date'] = $data['due_date'];
        }
        if (array_key_exists('vat_rate', $data)) {
            $updateData['vat_rate'] = $data['vat_rate'];
            $recalc = true;
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['customer_id'])) {
            $updateData['customer_id'] = $data['customer_id'];
        }
        if (isset($data['amount_paid'])) {
            $updateData['amount_paid'] = $data['amount_paid'];
        }

        if (! empty($data['items'])) {
            $invoice->items()->delete();
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $lineTotal;
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                ]);
            }
            $vatRate = $updateData['vat_rate'] ?? $invoice->vat_rate;
            $vatAmount = round($subtotal * $vatRate / 100, 2);
            $updateData['subtotal'] = $subtotal;
            $updateData['vat_amount'] = $vatAmount;
            $updateData['total'] = $subtotal + $vatAmount;
        }

        $invoice->update($updateData);

        return $invoice->fresh()->load(['customer', 'items']);
    }

    /**
     * Next invoice number for the current year.
     *
     * Takes a row lock over this year's invoices so two concurrent creates
     * cannot pick the same number and collide on the unique index. Only the
     * numeric suffix is read back, rather than the whole year's rows.
     */
    public function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $prefix = 'INV/'.$year.'/';

        $max = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('invoice_number')
            ->map(function ($number) use ($year) {
                if (preg_match('/^INV[\/_-]'.preg_quote($year, '/').'[\/_-](\d+)$/', (string) $number, $m)) {
                    return (int) $m[1];
                }

                return 0;
            })
            ->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Default VAT rate, from Settings when configured (falls back to UK 20%).
     */
    public static function defaultVatRate(): float
    {
        $configured = \App\Modules\Settings\Models\Setting::query()
            ->where('key', 'vat_rate')
            ->value('value');

        if ($configured === null || $configured === '' || ! is_numeric($configured)) {
            return 20.00;
        }

        return max(0.0, min(100.0, (float) $configured));
    }

    /**
     * Recomputes amount_paid and status from the invoice_payments ledger.
     *
     * The payments table is the single source of truth: nothing else may write
     * amount_paid, or the two mechanisms disagree and an invoice with no
     * payment rows can still read as "paid".
     */
    public function syncPaymentTotals(Invoice $invoice): void
    {
        $amountPaid = round((float) $invoice->payments()->sum('amount'), 2);
        $total = round((float) $invoice->total, 2);
        $status = $invoice->status;

        if ($amountPaid >= $total && $total > 0) {
            $status = 'paid';
        } elseif ($amountPaid > 0) {
            $status = 'partially_paid';
        } elseif (in_array($status, ['paid', 'partially_paid'], true)) {
            $status = 'sent';
        }

        $invoice->update([
            'amount_paid' => $amountPaid,
            'status' => $status,
        ]);
    }

    public function pdfFileName(Invoice $invoice): string
    {
        $invoice->loadMissing('customer');

        $customerName = $invoice->customer?->business_name ?: ($invoice->customer?->name ?? 'Customer');
        // Long business names produce unusable filenames on some systems.
        $customerName = \Illuminate\Support\Str::limit((string) $customerName, 60, '');
        $date = $invoice->invoice_date
            ? $invoice->invoice_date->format('Y-m-d')
            : now()->format('Y-m-d');

        $parts = [
            $customerName,
            $invoice->invoice_number,
            $date,
        ];

        $safe = collect($parts)
            ->map(fn ($part) => trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $part), '_'))
            ->filter()
            ->implode('-');

        return ($safe ?: 'invoice').'.pdf';
    }

    public function generatePDF(Invoice $invoice)
    {
        $invoice->load(['customer', 'items', 'payments.receivedBy']);

        $branding = PdfDocumentBranding::package();

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'logoUrl' => $branding['logoUrl'],
            'settings' => $branding['settings'],
        ])->setOption('enable-local-file-access', true)
            ->setOption('encoding', 'UTF-8')
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf;
    }

    public function sendEmail(Invoice $invoice, string $to, ?string $customMessage = null): void
    {
        $invoice->load(['customer', 'items']);
        $companyName = Setting::where('key', 'company_name')->first()?->value ?? config('app.name', 'Company');
        $logoSetting = Setting::where('key', 'logo_url')->first()?->value ?? '';
        $logoUrl = '';
        $logoPath = null;
        if ($logoSetting) {
            $logoUrl = str_starts_with($logoSetting, 'http')
                ? $logoSetting
                : rtrim(config('app.url'), '/').'/'.ltrim($logoSetting, '/');
            $cleanUrl = preg_replace('#^/storage/|^storage/#', '', trim($logoSetting, '/'));
            if ($cleanUrl) {
                $sp = storage_path('app/public/'.$cleanUrl);
                $pp = public_path('storage/'.$cleanUrl);
                $logoPath = file_exists($sp) ? $sp : (file_exists($pp) ? $pp : null);
            }
        }
        $customerName = $invoice->customer?->name ?? 'Customer';
        $socialFacebook = Setting::where('key', 'social_facebook_url')->first()?->value ?? '';
        $socialTwitter = Setting::where('key', 'social_twitter_url')->first()?->value ?? '';
        $socialLinkedIn = Setting::where('key', 'social_linkedin_url')->first()?->value ?? '';
        $socialInstagram = Setting::where('key', 'social_instagram_url')->first()?->value ?? '';
        $socialTikTok = Setting::where('key', 'social_tiktok_url')->first()?->value ?? '';
        $companyWebsite = Setting::where('key', 'company_website')->first()?->value ?? '';
        $companyPhone = Setting::where('key', 'company_phone')->first()?->value ?? '';
        $companyAddress = Setting::where('key', 'company_address')->first()?->value ?? '';
        $message = $customMessage ?: 'Please download your invoice from the attachment below.';
        \App\Services\MailConfigFromDatabase::apply();
        Mail::to($to)->send(new InvoiceEmail(
            $invoice,
            $message,
            $companyName,
            $logoUrl,
            $logoPath,
            $customerName,
            $socialFacebook,
            $socialTwitter,
            $socialLinkedIn,
            $socialInstagram,
            $socialTikTok,
            $companyWebsite,
            $companyPhone,
            $companyAddress
        ));
    }
}
