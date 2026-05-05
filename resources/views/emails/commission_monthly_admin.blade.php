<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php use App\Support\CommissionMoney; @endphp
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.5; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 15px; margin: 22px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table { border-collapse: collapse; width: 100%; margin: 8px 0 16px; font-size: 13px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; font-weight: 600; }
        .numeric { text-align: right; }
        .muted { color: #64748b; font-size: 12px; }
    </style>
</head>
<body>
    <p class="muted">{{ $companyName }}</p>
    <h1>Commission report — {{ $monthLabel }}</h1>

    @if(($overallTotals['GBP'] ?? 0) > 0 || ($overallTotals['PKR'] ?? 0) > 0)
        <p>
            Organisation totals:
            @if(($overallTotals['GBP'] ?? 0) > 0)<strong>{{ CommissionMoney::format('GBP', $overallTotals['GBP']) }}</strong>@endif
            @if(($overallTotals['PKR'] ?? 0) > 0)<span> &nbsp;</span><strong>{{ CommissionMoney::format('PKR', $overallTotals['PKR']) }}</strong>@endif
        </p>
    @endif

    <p>{{ $introduction }}</p>

    @foreach($userBlocks as $block)
        <h2>{{ $block['name'] }} <span class="muted">(#{{ $block['user_id'] }})</span></h2>
        @if(!empty($block['product_totals']))
            <p class="muted" style="margin:4px 0;">Product totals</p>
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Currency</th>
                    <th class="numeric">Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($block['product_totals'] as $pt)
                    <tr>
                        <td>{{ $pt['product'] }}</td>
                        <td>{{ $pt['currency'] }}</td>
                        <td class="numeric">{{ $pt['formatted_total'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($block['lines']))
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Role</th>
                    <th class="numeric">Amount</th>
                    <th>Recorded by</th>
                </tr>
                </thead>
                <tbody>
                @foreach($block['lines'] as $row)
                    <tr>
                        <td>{{ $row['created_at'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['commission_role_label'] }}</td>
                        <td class="numeric">{{ $row['formatted_amount'] }}</td>
                        <td>{{ $row['assigned_by_name'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <p class="muted" style="margin-top:20px;">
        Attached: full PDF (<code>{{ $pdf_filename }}</code>) for archiving. Individual staff were emailed separately with their own PDF.
    </p>
</body>
</html>
