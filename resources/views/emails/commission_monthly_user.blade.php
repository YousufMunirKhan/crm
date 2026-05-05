<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php use App\Support\CommissionMoney; @endphp
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.5; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; font-size: 13px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; font-weight: 600; }
        .numeric { text-align: right; }
        .muted { color: #64748b; font-size: 12px; }
        .pill { display: inline-block; padding: 2px 8px; background: #f1f5f9; border-radius: 6px; margin-right: 6px; }
    </style>
</head>
<body>
    <p class="muted">{{ $companyName }}</p>
    <h1>Your commission summary — {{ $monthLabel }}</h1>
    <p>Hi {{ $userName }}, please find attached a PDF breakdown of commissions recorded for you in {{ $monthLabel }}.</p>

    @if(($currencyTotals['GBP'] ?? 0) > 0 || ($currencyTotals['PKR'] ?? 0) > 0)
        <p>
            Totals:
            @if(($currencyTotals['GBP'] ?? 0) > 0)<span class="pill">{{ CommissionMoney::format('GBP', $currencyTotals['GBP']) }} GBP</span>@endif
            @if(($currencyTotals['PKR'] ?? 0) > 0)<span class="pill">{{ CommissionMoney::format('PKR', $currencyTotals['PKR']) }}</span>@endif
        </p>
    @endif

    @if(!empty($productTotals))
        <h2 style="font-size:16px;margin-top:20px;">By product</h2>
        <table role="presentation">
            <thead>
            <tr>
                <th>Product</th>
                <th>Currency</th>
                <th class="numeric">Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($productTotals as $row)
                <tr>
                    <td>{{ $row['product'] }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td class="numeric">{{ $row['formatted_total'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($detailRows))
        <h2 style="font-size:16px;">Detail</h2>
        <table role="presentation">
            <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Role</th>
                <th class="numeric">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($detailRows as $row)
                <tr>
                    <td>{{ $row['created_at'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td>{{ $row['commission_role_label'] }}</td>
                    <td class="numeric">{{ $row['formatted_amount'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(empty($detailRows))
        <p class="muted">No commission allocations were recorded for you in this month.</p>
    @endif

    <p class="muted" style="margin-top:24px;">Totals are based on CRM commission entries (allocation date).</p>
</body>
</html>
