@php
    use Carbon\Carbon;

    $companyName = $settings['company_name'] ?? config('app.name', 'Company');
    $companyEmail = $settings['company_email'] ?? '';
    $companyVat = $settings['company_vat'] ?? '';
    $companyCountry = $settings['company_country'] ?? 'United Kingdom';
    $customer = $invoice->customer;
    $customerName = $customer?->business_name ?: ($customer?->name ?? 'Customer');
    $customerLines = array_filter([
        $customer?->address,
        $customer?->city,
        $customer?->postcode,
        'United Kingdom',
    ]);

    $logoPath = null;
    if ($logoUrl ?? null) {
        $cleanUrl = preg_replace('#^/storage/|^storage/#', '', trim($logoUrl, '/'));
        $storagePath = storage_path('app/public/' . $cleanUrl);
        $publicPath = public_path('storage/' . $cleanUrl);
        $directPublic = public_path(ltrim((string) $logoUrl, '/'));
        if (file_exists($storagePath)) {
            $logoPath = str_replace('\\', '/', realpath($storagePath));
        } elseif (file_exists($publicPath)) {
            $logoPath = str_replace('\\', '/', realpath($publicPath));
        } elseif (file_exists($directPublic)) {
            $logoPath = str_replace('\\', '/', realpath($directPublic));
        }
    }
    if (!$logoPath && file_exists(public_path('images/logo.png'))) {
        $logoPath = str_replace('\\', '/', realpath(public_path('images/logo.png')));
    }

    $money = function ($amount) {
        return '&#163; ' . number_format((float) $amount, 2);
    };
    $dateFmt = function ($date) {
        return $date ? Carbon::parse($date)->format('m/d/Y') : '-';
    };
    $payments = $invoice->payments ?? collect();
    $fallbackPaid = $payments->isEmpty() && (float) $invoice->amount_paid > 0;
    $amountDue = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            background: #ffffff;
            font-size: 13px;
            line-height: 1.35;
        }
        .page {
            position: relative;
            min-height: 297mm;
            padding: 34px 34px 68px;
            overflow: hidden;
        }
        .top-shape {
            position: absolute;
            top: -128px;
            right: -130px;
            width: 520px;
            height: 300px;
            border-radius: 0 0 0 520px;
            background: #e8f2f2;
            z-index: 0;
        }
        .bottom-shape {
            position: absolute;
            left: -160px;
            bottom: -148px;
            width: 500px;
            height: 230px;
            border-radius: 0 500px 0 0;
            background: #eeeeee;
            z-index: 0;
        }
        .content {
            position: relative;
            z-index: 1;
        }
        .brand-row {
            display: table;
            width: 100%;
            min-height: 110px;
        }
        .tagline {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            padding-top: 10px;
            color: #0f7f83;
            font-size: 13px;
            font-weight: 700;
        }
        .company-block {
            display: table-cell;
            width: 55%;
            text-align: right;
            vertical-align: top;
            padding-top: 4px;
        }
        .logo {
            max-width: 120px;
            max-height: 42px;
            margin-bottom: 18px;
        }
        .company-name {
            font-size: 14px;
            letter-spacing: .2px;
            text-transform: uppercase;
        }
        .company-country {
            margin-top: 4px;
            font-size: 14px;
        }
        .customer-invoice-row {
            display: table;
            width: 100%;
            margin-top: 72px;
        }
        .customer-block {
            display: table-cell;
            width: 48%;
            vertical-align: bottom;
            font-size: 14px;
            line-height: 1.35;
        }
        .customer-name {
            font-weight: 600;
        }
        .invoice-title-wrap {
            display: table-cell;
            width: 52%;
            text-align: right;
            vertical-align: bottom;
        }
        .invoice-title {
            color: #137f83;
            font-size: 28px;
            font-weight: 400;
            letter-spacing: .2px;
        }
        .date-box {
            display: table;
            width: 100%;
            margin-top: 22px;
            border: 2px solid #222;
            border-radius: 10px;
            background: #eeeeee;
        }
        .date-cell {
            display: table-cell;
            width: 50%;
            padding: 10px 12px 11px;
        }
        .label {
            font-weight: 700;
            color: #222;
        }
        .date-value {
            margin-top: 4px;
            font-size: 14px;
        }
        .items {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 24px;
            border: 1px solid #4b5563;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            overflow: hidden;
        }
        .items th {
            background: #147f83;
            color: #ffffff;
            padding: 10px 8px;
            font-size: 14px;
            font-weight: 400;
            text-align: left;
            border-right: 1px solid #a7c6c8;
        }
        .items th:last-child,
        .items td:last-child {
            border-right: 0;
        }
        .items td {
            padding: 10px 8px;
            border-top: 1px solid #9ca3af;
            border-right: 1px solid #9ca3af;
            font-size: 13px;
            vertical-align: top;
        }
        .items .right {
            text-align: right;
        }
        .items .center {
            text-align: center;
        }
        .description-note {
            display: block;
            margin-top: 9px;
            font-style: italic;
        }
        .totals-wrap {
            width: 42%;
            margin-left: auto;
            margin-top: 38px;
        }
        .totals {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #6b7280;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }
        .totals td {
            padding: 9px 10px;
            border-bottom: 1px solid #9ca3af;
            border-right: 1px solid #9ca3af;
            font-size: 13px;
        }
        .totals tr:last-child td {
            border-bottom: 0;
        }
        .totals td:last-child {
            border-right: 0;
            text-align: right;
            white-space: nowrap;
        }
        .total-row td {
            background: #147f83;
            color: #ffffff;
            font-weight: 700;
        }
        .payment-line-label {
            font-style: italic;
        }
        .amount-due td {
            font-weight: 700;
        }
        .payment-info {
            margin-top: 42px;
            font-size: 13px;
        }
        .payment-communication {
            font-weight: 700;
            margin-bottom: 24px;
        }
        .muted {
            color: #5f6472;
        }
        .payment-detail-line {
            margin-top: 5px;
        }
        .vat-block {
            margin-top: 26px;
            font-weight: 700;
            color: #5f6472;
        }
        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 22px;
            text-align: center;
            z-index: 1;
            font-size: 13px;
        }
        .page-count {
            margin-top: 20px;
            color: #5f6472;
        }
        .page-count:after {
            content: "Page " counter(page) " / " counter(pages);
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="top-shape"></div>
        <div class="bottom-shape"></div>

        <div class="content">
            <div class="brand-row">
                <div class="tagline">Smart Solutions for Smart Businesses</div>
                <div class="company-block">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="Logo" class="logo">
                    @endif
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-country">{{ $companyCountry }}</div>
                </div>
            </div>

            <div class="customer-invoice-row">
                <div class="customer-block">
                    <div class="customer-name">{{ $customerName }}</div>
                    @foreach($customerLines as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                </div>
                <div class="invoice-title-wrap">
                    <div class="invoice-title">Invoice {{ $invoice->invoice_number }}</div>
                </div>
            </div>

            <div class="date-box">
                <div class="date-cell">
                    <div class="label">Invoice Date</div>
                    <div class="date-value">{{ $dateFmt($invoice->invoice_date) }}</div>
                </div>
                <div class="date-cell">
                    <div class="label">Due Date</div>
                    <div class="date-value">{{ $dateFmt($invoice->due_date) }}</div>
                </div>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 34%;">DESCRIPTION</th>
                        <th class="right" style="width: 20%;">QUANTITY</th>
                        <th class="right" style="width: 18%;">UNIT PRICE</th>
                        <th class="right" style="width: 11%;">TAXES</th>
                        <th class="right" style="width: 17%;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoice->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="right">{{ number_format((float) $invoice->vat_rate, 0) }}%</td>
                            <td class="right">{!! $money($item->line_total) !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="center">No items</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="totals-wrap">
                <table class="totals">
                    <tr>
                        <td>Untaxed Amount</td>
                        <td>{!! $money($invoice->subtotal) !!}</td>
                    </tr>
                    @if((float) $invoice->vat_amount > 0)
                        <tr>
                            <td>TAX {{ number_format((float) $invoice->vat_rate, 0) }}%</td>
                            <td>{!! $money($invoice->vat_amount) !!}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total</td>
                        <td>{!! $money($invoice->total) !!}</td>
                    </tr>
                    @foreach($payments as $payment)
                        <tr>
                            <td class="payment-line-label">Paid on {{ $dateFmt($payment->payment_date) }}</td>
                            <td>{!! $money($payment->amount) !!}</td>
                        </tr>
                    @endforeach
                    @if($fallbackPaid)
                        <tr>
                            <td class="payment-line-label">Paid</td>
                            <td>{!! $money($invoice->amount_paid) !!}</td>
                        </tr>
                    @endif
                    @if((float) $invoice->amount_paid > 0)
                        <tr class="amount-due">
                            <td>Amount Due</td>
                            <td>{!! $money($amountDue) !!}</td>
                        </tr>
                    @endif
                </table>
            </div>

            <div class="payment-info">
                <div class="payment-communication">Payment Communication: {{ $invoice->invoice_number }}</div>
                <div class="muted">
                    <div class="payment-detail-line">Payment Details:</div>
                    <div class="payment-detail-line">Payment can be made to the following account:</div>
                    @if($settings['payment_account_name'] ?? null)
                        <div class="payment-detail-line">Account Name: {{ $settings['payment_account_name'] }}</div>
                    @endif
                    @if($settings['payment_sort_code'] ?? null)
                        <div class="payment-detail-line">Sort Code: {{ $settings['payment_sort_code'] }}</div>
                    @endif
                    @if($settings['payment_account_number'] ?? null)
                        <div class="payment-detail-line">Account Number: {{ $settings['payment_account_number'] }}</div>
                    @endif
                </div>

                @if($companyVat)
                    <div class="vat-block">
                        <div>VAT Registration No:</div>
                        <div>{{ $companyVat }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="footer">
            <div>{{ $companyEmail }}</div>
            <div class="page-count"></div>
        </div>
    </div>
</body>
</html>
