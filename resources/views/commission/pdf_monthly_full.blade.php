<!DOCTYPE html>
<html lang="en">
@php use App\Support\CommissionMoney; @endphp
<head>
    <meta charset="utf-8"/>
    <title>Commission report — {{ $monthLabel }}</title>
    @include('partials.pdf_branding_styles')
</head>
<body>
<div class="container">
    @include('partials.pdf_branding_header')

    <div class="commission-pdf-main">
        <div class="page-break-avoid">
            <div class="section-title">Commission report</div>
            <div class="commission-doc-title">{{ $companyName }}</div>
            <p class="commission-sub">{{ $monthLabel }}</p>
        </div>

        @if(($overallTotals['GBP'] ?? 0) > 0 || ($overallTotals['PKR'] ?? 0) > 0)
            <div class="page-break-avoid" style="margin-bottom: 14px;">
                <p style="font-size: 9px; color: #64748b; margin-bottom: 4px;">Organisation totals for this report.</p>
                <div class="commission-summary-totals" style="margin-top: 0;">
                    <table class="totals-table">
                        @if(($overallTotals['GBP'] ?? 0) > 0)
                            <tr>
                                <td class="label">Total (GBP)</td>
                                <td class="value">{{ CommissionMoney::format('GBP', $overallTotals['GBP']) }}</td>
                            </tr>
                        @endif
                        @if(($overallTotals['PKR'] ?? 0) > 0)
                            <tr>
                                <td class="label">Total (PKR)</td>
                                <td class="value">{{ CommissionMoney::format('PKR', $overallTotals['PKR']) }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        @endif

        @forelse($userBlocks as $userBlock)
            <div class="user-block-heading">
                {{ $userBlock['name'] }}
                <span class="pdf-badge">ID {{ $userBlock['user_id'] ?? '' }}</span>
            </div>

            @if(!empty($userBlock['product_totals']))
                <div class="section-title" style="margin-top: 4px;">By product</div>
                @php $n = count($userBlock['product_totals']); @endphp
                <table class="items-table commission-items-table{{ $n > 8 ? ' items-table-break' : '' }}">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Currency</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Lines</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($userBlock['product_totals'] as $pt)
                        <tr>
                            <td>{{ $pt['product'] }}</td>
                            <td class="text-center">{{ $pt['currency'] }}</td>
                            <td class="text-right">{{ $pt['formatted_total'] }}</td>
                            <td class="text-right">{{ $pt['lines'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($userBlock['lines']))
                <div class="section-title" style="margin-top: 4px;">Allocations</div>
                @php $ln = count($userBlock['lines']); @endphp
                <table class="items-table commission-items-table{{ $ln > 10 ? ' items-table-break' : '' }}">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Role</th>
                        <th class="text-right">Amount</th>
                        <th>Recorded by</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($userBlock['lines'] as $row)
                        <tr>
                            <td>{{ $row['created_at'] }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['product_name'] }}</td>
                            <td>{{ $row['commission_role_label'] ?? CommissionMoney::humanizeRole($row['commission_role'] ?? null) }}</td>
                            <td class="text-right">{{ $row['formatted_amount'] }}</td>
                            <td>{{ $row['assigned_by_name'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="commission-sub">No rows.</p>
            @endif

        @empty
            <p class="commission-sub">No commission allocations in this period.</p>
        @endforelse

        @php
            $pdfFooterNote = 'Confidential — internal use. One row per recorded commission allocation. Generated '.$generatedAt.'.';
        @endphp
        @include('partials.pdf_branding_footer')
    </div>
</div>
</body>
</html>
