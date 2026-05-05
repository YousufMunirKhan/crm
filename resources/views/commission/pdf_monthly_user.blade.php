<!DOCTYPE html>
<html lang="en">
@php use App\Support\CommissionMoney; @endphp
<head>
    <meta charset="utf-8"/>
    <title>Commission summary — {{ $monthLabel }}</title>
    @include('partials.pdf_branding_styles')
</head>
<body>
<div class="container">
    @include('partials.pdf_branding_header')

    <div class="commission-pdf-main">
        <div class="page-break-avoid">
            <div class="section-title">Commission summary</div>
            <div class="commission-doc-title">{{ $userName }}</div>
            <p class="commission-sub">{{ $companyName }} — {{ $monthLabel }}</p>
        </div>

        @if (!empty($productTotals))
            <div class="section-title" style="margin-top: 8px;">Totals by product</div>
            @php $ptCount = count($productTotals); @endphp
            <table class="items-table commission-items-table{{ $ptCount > 10 ? ' items-table-break' : '' }}">
                <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-center">Currency</th>
                    <th class="text-right">Total commission</th>
                    <th class="text-right">Allocations</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($productTotals as $row)
                    <tr>
                        <td>{{ $row['product'] }}</td>
                        <td class="text-center">{{ $row['currency'] }}</td>
                        <td class="text-right">{{ $row['formatted_total'] }}</td>
                        <td class="text-right">{{ $row['lines'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if (!empty($detailRows))
            <div class="section-title" style="margin-top: 8px;">Allocated lines</div>
            @php $drCount = count($detailRows); @endphp
            <table class="items-table commission-items-table{{ $drCount > 12 ? ' items-table-break' : '' }}">
                <thead>
                <tr>
                    <th>Date recorded</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Role</th>
                    <th class="text-right">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($detailRows as $row)
                    <tr>
                        <td>{{ $row['created_at'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['commission_role_label'] ?? CommissionMoney::humanizeRole($row['commission_role'] ?? null) }}</td>
                        <td class="text-right">{{ $row['formatted_amount'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="commission-sub" style="margin-top: 12px;">No commission allocations for this period.</p>
        @endif

        @if(($currencyTotals['GBP'] ?? 0) > 0 || ($currencyTotals['PKR'] ?? 0) > 0)
            <div class="commission-summary-totals">
                <table class="totals-table">
                    @if(($currencyTotals['GBP'] ?? 0) > 0)
                        <tr>
                            <td class="label">Total (GBP)</td>
                            <td class="value">{{ CommissionMoney::format('GBP', $currencyTotals['GBP']) }}</td>
                        </tr>
                    @endif
                    @if(($currencyTotals['PKR'] ?? 0) > 0)
                        <tr>
                            <td class="label">Total (PKR)</td>
                            <td class="value">{{ CommissionMoney::format('PKR', $currencyTotals['PKR']) }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        @endif

        @php
            $pdfFooterNote = 'Commission figures use the date each allocation was recorded in the CRM. Generated '.$generatedAt.'.';
        @endphp
        @include('partials.pdf_branding_footer')
    </div>
</div>
</body>
</html>
