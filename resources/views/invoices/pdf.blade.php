<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 12mm 12mm 12mm;
        }
        @font-face {
            font-family: 'DejaVu Sans';
            src: url('{{ storage_path('fonts/DejaVuSans.ttf') }}') format('truetype');
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #1a1a2e;
            line-height: 1.45;
            background: #fff;
        }
        .container {
            max-width: 100%;
            padding: 0 5px;
        }
        .page-break-avoid {
            page-break-inside: avoid;
        }

        /* Top bar */
        .top-bar {
            height: 4px;
            background: #0d9488;
            margin: 0 -5px 15px -5px;
        }
        
        /* Logo - centered, bigger */
        .logo-section {
            text-align: center;
            margin-bottom: 36px;
            page-break-inside: avoid;
        }
        .logo-img {
            max-height: 85px;
            max-width: 240px;
        }
        .logo-fallback {
            font-size: 30px;
            font-weight: bold;
            color: #1a1a2e;
        }
        
        /* Two columns: Invoice (left), Bill To (right) */
        .invoice-header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .invoice-info-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: left;
            padding-right: 15px;
        }
        .bill-to-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-left: 15px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #0d9488;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #0d9488;
            letter-spacing: 0.5px;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            color: #0d9488;
            margin-bottom: 12px;
            letter-spacing: 2px;
        }
        .customer-name {
            font-weight: bold;
            font-size: 14px;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        .customer-details {
            color: #555;
            line-height: 1.5;
            font-size: 11px;
        }
        .invoice-meta-table {
            border-collapse: collapse;
        }
        .invoice-meta-table td {
            padding: 3px 0;
            font-size: 12px;
        }
        .invoice-meta-table .label {
            color: #666;
            padding-right: 10px;
            text-align: left;
            font-weight: 500;
        }
        .invoice-meta-table .value {
            color: #1a1a2e;
            font-weight: 600;
            text-align: right;
        }
        
        /* Items Table - clear headers, DomPDF friendly */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            margin-top: 12px;
            page-break-inside: auto;
        }
        .items-table thead tr {
            background-color: #6b7280;
        }
        .items-table th {
            color: #ffffff;
            font-weight: bold;
            font-size: 12px;
            padding: 10px 12px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #4b5563;
        }
        .items-table th.text-right {
            text-align: right;
        }
        .items-table th.text-center {
            text-align: center;
        }
        .items-table td {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            font-size: 12px;
            vertical-align: middle;
        }
        .items-table td.text-right {
            text-align: right;
        }
        .items-table td.text-center {
            text-align: center;
        }
        .items-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .items-table:not(.items-table-break) {
            page-break-inside: avoid;
        }
        .items-table.items-table-break tbody tr {
            page-break-inside: avoid;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-paid {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-draft {
            background-color: #f1f5f9;
            color: #475569;
        }
        .status-partially_paid {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        /* Totals - Total Payable with light blue/teal like image 2 */
        .totals-section {
            display: table;
            width: 100%;
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .totals-spacer {
            display: table-cell;
            width: 60%;
        }
        .totals-box {
            display: table-cell;
            width: 40%;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 12px;
            font-size: 12px;
        }
        .totals-table .label {
            text-align: right;
            color: #555;
            padding-right: 12px;
            font-weight: 500;
        }
        .totals-table .value {
            text-align: right;
            color: #1a1a2e;
            font-weight: 600;
        }
        .totals-table tr.total-row {
            background-color: #0d9488;
            border: none;
        }
        .totals-table tr.total-row td {
            color: #fff;
            font-weight: bold;
            font-size: 15px;
            padding: 12px 14px;
        }
        .totals-table tr.total-row .label {
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        /* Payment Details - more space from Total Payable */
        .payment-section {
            margin-top: 72px;
            padding-top: 36px;
            padding-bottom: 14px;
            padding-left: 16px;
            padding-right: 16px;
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            page-break-inside: avoid;
        }
        .payment-title {
            font-size: 13px;
            font-weight: bold;
            color: #166534;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .payment-details {
            display: table;
            width: 100%;
        }
        .payment-row {
            display: table-row;
        }
        .payment-label {
            display: table-cell;
            padding: 4px 0;
            color: #475569;
            width: 140px;
            font-weight: 600;
            font-size: 11px;
        }
        .payment-value {
            display: table-cell;
            padding: 4px 0;
            color: #1a1a2e;
            font-weight: 700;
            font-size: 12px;
        }
        
        /* Footer - compact */
        .footer {
            margin-top: 24px;
            padding: 14px 16px;
            border-top: 2px solid #0d9488;
            font-size: 12px;
            color: #666;
            background-color: #f8fafc;
            page-break-inside: avoid;
        }
        .footer-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .footer-col {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            padding: 0 8px;
        }
        .footer-col.center {
            text-align: center;
        }
        .footer-col.right {
            text-align: right;
        }
        .footer-note {
            text-align: center;
            margin-top: 12px;
            font-style: italic;
            color: #0d9488;
            font-weight: 500;
            font-size: 11px;
        }
        .footer-label {
            font-weight: 700;
            color: #0d9488;
            margin-bottom: 2px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top bar -->
        <div class="top-bar"></div>

        <!-- Logo - centered, bigger -->
        <div class="logo-section page-break-avoid">
            @php
                $logoPath = null;
                if ($logoUrl ?? null) {
                    $cleanUrl = preg_replace('#^/storage/|^storage/#', '', trim($logoUrl, '/'));
                    $storagePath = storage_path('app/public/' . $cleanUrl);
                    $publicPath = public_path('storage/' . $cleanUrl);
                    if (file_exists($storagePath)) {
                        $logoPath = str_replace('\\', '/', realpath($storagePath));
                    } elseif (file_exists($publicPath)) {
                        $logoPath = str_replace('\\', '/', realpath($publicPath));
                    } elseif (file_exists(public_path($logoUrl))) {
                        $logoPath = str_replace('\\', '/', realpath(public_path($logoUrl)));
                    } else {
                        $pubPath = public_path(ltrim($logoUrl, '/'));
                        if (file_exists($pubPath)) {
                            $logoPath = str_replace('\\', '/', realpath($pubPath));
                        }
                    }
                }
            @endphp
            @if($logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Logo" class="logo-img">
            @elseif(file_exists(public_path('images/logo.png')))
                <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo-img">
            @else
                <span class="logo-fallback">{{ $settings['company_name'] ?? '' }}</span>
            @endif
        </div>

        <!-- Three columns: Invoice (left), Business (center), Bill To (right) -->
        <div class="invoice-header page-break-avoid">
            <div class="invoice-info-section">
                <div class="section-title">Invoice</div>
                <table class="invoice-meta-table">
                    <tr>
                        <td class="label">Invoice No:</td>
                        <td class="value">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Issue Date:</td>
                        <td class="value">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                    </tr>
                    @if($invoice->due_date)
                    <tr>
                        <td class="label">Due Date:</td>
                        <td class="value">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</td>
                    </tr>
                    @endif
                    @if($invoice->period)
                    <tr>
                        <td class="label">Period:</td>
                        <td class="value">{{ $invoice->period }}</td>
                    </tr>
                    @endif
                </table>
            </div>
            <div class="bill-to-section">
                <div class="section-title">Bill To</div>
                <div class="customer-name">{{ $invoice->customer->name }}</div>
                <div class="customer-details">
                    @if($invoice->customer->address)
                        {{ $invoice->customer->address }}<br>
                    @endif
                    @if($invoice->customer->city || $invoice->customer->postcode)
                        {{ $invoice->customer->city }}{{ $invoice->customer->city && $invoice->customer->postcode ? ', ' : '' }}{{ $invoice->customer->postcode }}<br>
                    @endif
                    @if($invoice->customer->phone)
                        {{ $invoice->customer->phone }}<br>
                    @endif
                    @if($invoice->customer->email)
                        {{ $invoice->customer->email }}<br>
                    @endif
                    @if($invoice->customer->vat_number)
                        VAT: {{ $invoice->customer->vat_number }}
                    @endif
                </div>
            </div>
        </div>

        <!-- Items Table - headers always visible -->
        @php $itemCount = count($invoice->items); @endphp
        <table class="items-table{{ $itemCount > 7 ? ' items-table-break' : '' }}">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 42%;">Product / Description</th>
                    <th class="text-center" style="width: 14%;">Quantity</th>
                    <th class="text-right" style="width: 18%;">Unit Price</th>
                    <th class="text-right" style="width: 22%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td style="color: #64748b; font-weight: 600;">{{ $index + 1 }}</td>
                    <td>
                        <strong style="color: #1a1a2e;">{{ $item->description }}</strong>
                    </td>
                    <td class="text-center" style="color: #475569; font-weight: 600;">{{ $item->quantity }}</td>
                    <td class="text-right" style="color: #475569;">£{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right"><strong style="color: #0d9488; font-size: 12px;">£{{ number_format($item->line_total, 2) }}</strong></td>
                </tr>
                @endforeach
                @if(count($invoice->items) == 0)
                <tr>
                    <td colspan="5" style="text-align: center; color: #888; padding: 20px;">No items</td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-spacer"></div>
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal:</td>
                        <td class="value">£{{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    @if($invoice->vat_amount > 0)
                    <tr>
                        <td class="label">VAT ({{ number_format($invoice->vat_rate, 0) }}%):</td>
                        <td class="value">£{{ number_format($invoice->vat_amount, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td class="label">Total Payable:</td>
                        <td class="value">£{{ number_format($invoice->total, 2) }}</td>
                    </tr>
                    @if($invoice->amount_paid > 0 && $invoice->amount_paid < $invoice->total)
                    <tr>
                        <td class="label">Amount Paid:</td>
                        <td class="value">£{{ number_format($invoice->amount_paid, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label" style="font-weight: bold; color: #dc2626;">Balance Due:</td>
                        <td class="value" style="font-weight: bold; color: #dc2626;">£{{ number_format($invoice->total - $invoice->amount_paid, 2) }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- Payment Details -->
        @if($invoice->status !== 'paid')
        <div class="payment-section">
            <div class="payment-title">PAYMENT DETAILS</div>
            <p style="margin-bottom: 12px; color: #555; font-size: 12px;">Payment can be made to the following account:</p>
            <div class="payment-details">
                <div class="payment-row">
                    <span class="payment-label">Account Name:</span>
                    <span class="payment-value">{{ $settings['payment_account_name'] ?? $settings['company_name'] ?? '' }}</span>
                </div>
                @if($settings['payment_sort_code'] ?? null)
                <div class="payment-row">
                    <span class="payment-label">Sort Code:</span>
                    <span class="payment-value">{{ $settings['payment_sort_code'] }}</span>
                </div>
                @endif
                @if($settings['payment_account_number'] ?? null)
                <div class="payment-row">
                    <span class="payment-label">Account Number:</span>
                    <span class="payment-value">{{ $settings['payment_account_number'] }}</span>
                </div>
                @endif
                <div class="payment-row">
                    <span class="payment-label">Payment Terms:</span>
                    <span class="payment-value">
                        @if($invoice->due_date)
                            Due {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
                        @else
                            {{ $settings['payment_terms_note'] ?? 'As per agreement' }}
                        @endif
                    </span>
                </div>
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-row">
                <div class="footer-col">
                    @if($settings['company_registration_no'] ?? null)
                        <div class="footer-label">Company Registration:</div>
                        <div>{{ $settings['company_registration_no'] }}</div>
                    @endif
                </div>
                <div class="footer-col center">
                    @if($settings['company_address'] ?? null)
                        <div class="footer-label">Address:</div>
                        <div>{{ $settings['company_address'] }}</div>
                    @endif
                </div>
                <div class="footer-col right">
                    @if($settings['company_vat'] ?? null)
                        <div class="footer-label">VAT Registration:</div>
                        <div>{{ $settings['company_vat'] }}</div>
                    @endif
                </div>
            </div>
            <div class="footer-row" style="margin-top: 10px;">
                <div class="footer-col">
                    @if($settings['company_phone'] ?? null)
                        <div class="footer-label">Phone:</div>
                        <div>{{ $settings['company_phone'] }}</div>
                    @endif
                </div>
                <div class="footer-col center">
                    @if($settings['company_email'] ?? null)
                        <div class="footer-label">Email:</div>
                        <div>{{ $settings['company_email'] }}</div>
                    @endif
                </div>
                <div class="footer-col right">
                    @if($settings['company_website'] ?? null)
                        <div class="footer-label">Website:</div>
                        <div>{{ $settings['company_website'] }}</div>
                    @endif
                </div>
            </div>
            @if(!empty($settings['payment_terms_note'] ?? null))
            <div class="footer-note" style="margin-top: 8px; text-align: left;">
                {{ $settings['payment_terms_note'] }}
            </div>
            @endif
            <div class="footer-note">
                Thank you for your business!
            </div>
        </div>
    </div>
</body>
</html>
