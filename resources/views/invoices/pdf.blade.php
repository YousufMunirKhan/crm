<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @include('partials.pdf_branding_styles')
</head>
<body>
    <div class="container">
        @include('partials.pdf_branding_header')

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
                    <td class="text-right"><strong style="color: #0d9488; font-size: 10px;">£{{ number_format($item->line_total, 2) }}</strong></td>
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
            <p style="margin-bottom: 8px; color: #555; font-size: 10px;">Payment can be made to the following account:</p>
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

        @include('partials.pdf_branding_footer')
    </div>
</body>
</html>
