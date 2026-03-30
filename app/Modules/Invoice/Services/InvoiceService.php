<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\CRM\Models\Customer;
use App\Modules\Settings\Models\Setting;
use App\Mail\InvoiceEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

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

        $vatRate = $data['vat_rate'] ?? 20.00; // UK VAT default
        $vatAmount = round($subtotal * $vatRate / 100, 2);
        $total = $subtotal + $vatAmount;

        $invoice = Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'customer_id' => $customer->id,
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
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return $invoice->load('items');
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

        if (!empty($data['items'])) {
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

    public function generateInvoiceNumber(): string
    {
        return 'INV-' . date('Y') . '-' . strtoupper(Str::random(8));
    }

    public function generatePDF(Invoice $invoice)
    {
        $invoice->load(['customer', 'items']);
        
        // Get logo and company details from settings
        $logoUrl = \App\Modules\Settings\Models\Setting::where('key', 'logo_url')->first()?->value;
        $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_name',
            'company_address',
            'company_phone',
            'company_email',
            'company_website',
            'company_registration_no',
            'company_vat',
            'payment_account_name',
            'payment_sort_code',
            'payment_account_number',
            'payment_terms_note',
        ])->pluck('value', 'key');
        
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'logoUrl' => $logoUrl,
            'settings' => $settings,
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
                : rtrim(config('app.url'), '/') . '/' . ltrim($logoSetting, '/');
            $cleanUrl = preg_replace('#^/storage/|^storage/#', '', trim($logoSetting, '/'));
            if ($cleanUrl) {
                $sp = storage_path('app/public/' . $cleanUrl);
                $pp = public_path('storage/' . $cleanUrl);
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
        $message = $customMessage ?: "Please download your invoice from the attachment below.";
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


