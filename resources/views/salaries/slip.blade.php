@php
    // Use proper currency symbols
    $currencySymbol = $salary->currency === 'PKR' ? 'Rs' : '£';
    
    // Get all company settings
    $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
        'logo_url',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'company_registration_no',
        'company_vat'
    ])->pluck('value', 'key');
    
    $logoUrl = $settings['logo_url'] ?? null;
    $cleanUrl = $logoUrl ? preg_replace('#^/storage/|^storage/#', '', trim($logoUrl, '/')) : '';
    $storagePath = $cleanUrl ? storage_path('app/public/' . $cleanUrl) : null;
    $publicPath = $cleanUrl ? public_path('storage/' . $cleanUrl) : null;
    $logoPath = null;
    if ($storagePath && file_exists($storagePath)) {
        $logoPath = str_replace('\\', '/', realpath($storagePath));
    } elseif ($publicPath && file_exists($publicPath)) {
        $logoPath = str_replace('\\', '/', realpath($publicPath));
    }
    
    // Company details with fallbacks
    $companyName = $settings['company_name'] ?? 'SWITCH&SAVE BUSINESS SERVICES LTD';
    $companyAddress = $settings['company_address'] ?? '3A Perry Common Road, Erdington<br>Birmingham, B23 7AB';
    $companyPhone = $settings['company_phone'] ?? '+44 7340 529757';
    $companyEmail = $settings['company_email'] ?? 'hello@switch-and-save.uk';
    $companyWebsite = $settings['company_website'] ?? 'https://switch-and-save.uk';
    $companyRegNo = $settings['company_registration_no'] ?? null;
    $companyVat = $settings['company_vat'] ?? null;
    
    // Calculate totals
    $transportAllowance = $salary->allowances ?? 0;
    $houseAllowance = $salary->house_allowance ?? 0;
    $medicalAllowance = $salary->medical_allowance ?? 0;
    $otherAllowance = $salary->other_allowance ?? 0;
    $totalBonuses = collect($salary->bonuses ?? [])->sum('amount');
    $tax = $salary->tax ?? 0;
    $loanDeduction = $salary->loan_deduction ?? 0;
    $otherDeduction = $salary->other_deduction ?? 0;
    $totalDeductions = $tax + $loanDeduction + $otherDeduction + collect($salary->deductions_detail ?? [])->sum('amount');
    $grossSalary = $salary->base_salary + $transportAllowance + $houseAllowance + $medicalAllowance + $otherAllowance + $totalBonuses;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $salary->month }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        @page {
            margin: 10mm;
            size: A4;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1a1a2e;
            line-height: 1.4;
            background: #fff;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 8px 12px;
            page-break-inside: avoid;
        }
        
        /* Header */
        .header {
            margin-bottom: 12px;
            text-align: center;
            page-break-inside: avoid;
        }
        .logo-container {
            margin-bottom: 6px;
            text-align: center;
        }
        .logo-img {
            max-height: 55px;
            width: auto;
            display: block;
            margin: 0 auto;
        }
        .logo-text {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a2e;
            letter-spacing: -0.5px;
            text-align: center;
        }
        .logo-text .ampersand {
            color: #22c55e;
        }
        .tagline {
            font-size: 8px;
            color: #666;
            font-style: italic;
            margin-top: 2px;
            text-align: center;
        }
        .company-details {
            margin-top: 8px;
            font-size: 8px;
            color: #555;
            line-height: 1.5;
            text-align: center;
        }
        
        /* Salary Slip Header */
        .slip-header-wrapper {
            margin-top: 15px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .slip-title-centered {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 15px;
        }
        .slip-header {
            display: block;
            width: 100%;
        }
        .employee-section {
            width: 100%;
            vertical-align: top;
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            color: #22c55e;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 2px solid #22c55e;
            display: inline-block;
        }
        .employee-name {
            font-weight: bold;
            font-size: 12px;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .employee-details {
            font-size: 9px;
            color: #666;
            line-height: 2;
            margin-top: 5px;
        }
        .detail-row {
            margin-bottom: 5px;
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            width: 110px;
            display: table-cell;
            vertical-align: middle;
            padding-right: 8px;
        }
        .detail-value {
            color: #333;
            display: table-cell;
            vertical-align: middle;
        }
        .info-row {
            margin-bottom: 5px;
            font-size: 9px;
            text-align: right;
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            display: table-cell;
            text-align: right;
            width: 50px;
            padding-right: 8px;
            vertical-align: middle;
        }
        .info-value {
            color: #333;
            font-weight: 500;
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }
        
        /* Salary Details Tables */
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 8px;
        }
        .salary-table th {
            background-color: #0d9488;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .salary-table th.right-align {
            text-align: right;
        }
        .salary-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        .salary-table tr:last-child td {
            border-bottom: none;
        }
        .salary-table .label {
            font-weight: 600;
            color: #1a1a2e;
            width: 65%;
        }
        .salary-table .amount {
            text-align: right;
            color: #1a1a2e;
            font-weight: 500;
        }
        .salary-table .positive {
            color: #1a1a2e;
        }
        .salary-table .negative {
            color: #ef4444;
        }
        
        /* Totals */
        .totals-section {
            margin-top: 8px;
            padding: 8px;
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 4px 0;
            font-size: 10px;
        }
        .totals-table .label {
            font-weight: 600;
            color: #555;
            width: 70%;
        }
        .totals-table .amount {
            text-align: right;
            font-weight: bold;
            color: #1a1a2e;
        }
        /* Net Salary */
        .net-salary-section {
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .net-salary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .net-salary-table td {
            padding: 8px 10px;
            font-size: 11px;
            border: 2px solid #1a1a2e;
        }
        .net-salary-table .label {
            text-align: left;
            font-weight: bold;
            color: #1a1a2e;
        }
        .net-salary-table .amount {
            text-align: right;
            font-weight: bold;
            color: #1a1a2e;
        }
        
        /* Attendance Summary */
        .attendance-section {
            margin-top: 8px;
            padding: 8px;
            background-color: #f8fafc;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        .attendance-title {
            font-size: 9px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .attendance-info {
            font-size: 9px;
            color: #666;
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 8px;
            color: #666;
            page-break-inside: avoid;
        }
        .footer-company {
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 4px;
            font-size: 9px;
        }
        .footer-details {
            line-height: 1.6;
            margin-bottom: 4px;
        }
        .footer-website {
            margin-top: 4px;
            color: #0d9488;
        }
        .footer-legal {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 7px;
            color: #999;
        }
        .notes {
            margin-top: 8px;
            padding: 6px;
            background-color: #fef3c7;
            border-left: 3px solid #f59e0b;
            font-size: 9px;
            color: #92400e;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                @if($logoPath && file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="Company Logo" class="logo-img">
                @elseif(file_exists(public_path('images/logo.png')))
                    <img src="{{ public_path('images/logo.png') }}" alt="Company Logo" class="logo-img">
                @else
                    <div class="logo-text">SWITCH<span class="ampersand">&</span>SAVE</div>
                @endif
            </div>
        </div>

        <!-- Salary Slip Header -->
        <div class="slip-header-wrapper">
            <div class="slip-title-centered">SALARY SLIP</div>
            <div class="slip-header">
                <div class="employee-section">
                    <div class="section-title">Employee Details</div>
                    <div class="employee-name">{{ $salary->user->name }}</div>
                    <div class="employee-details">
                        <div class="detail-row">
                            <span class="detail-label">Employee ID:</span>
                            <span class="detail-value">EMP{{ str_pad($salary->user->id, 3, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Designation:</span>
                            <span class="detail-value">{{ $salary->user->role->name ?? 'N/A' }}</span>
                        </div>
                        @if($salary->user->email)
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value">{{ $salary->user->email }}</span>
                        </div>
                        @endif
                        @if($salary->user->phone)
                        <div class="detail-row">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value">{{ $salary->user->phone }}</span>
                        </div>
                        @endif
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">{{ now()->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earnings Section -->
        <table class="salary-table">
            <thead>
                <tr>
                    <th class="left-align">EARNINGS</th>
                    <th class="right-align">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="label">Basic Salary</td>
                    <td class="amount positive">{{ $currencySymbol }} {{ number_format($salary->base_salary, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">House Allowance</td>
                    <td class="amount positive">{{ $currencySymbol }} {{ number_format($houseAllowance, 2) }}</td>
                </tr>
                @if($totalBonuses > 0)
                    @foreach($salary->bonuses as $bonus)
                    <tr>
                        <td class="label">{{ $bonus['name'] ?? 'Sales Commission' }}</td>
                        <td class="amount positive">{{ $currencySymbol }} {{ number_format($bonus['amount'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                @else
                <tr>
                    <td class="label">Sales Commission</td>
                    <td class="amount positive">{{ $currencySymbol }}0.00</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Transport Allowance</td>
                    <td class="amount positive">{{ $currencySymbol }} {{ number_format($transportAllowance, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Medical Allowance</td>
                    <td class="amount positive">{{ $currencySymbol }} {{ number_format($medicalAllowance, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Other Allowance</td>
                    <td class="amount positive">{{ $currencySymbol }} {{ number_format($otherAllowance, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label bold">Total Earnings</td>
                    <td class="amount positive bold">{{ $currencySymbol }} {{ number_format($grossSalary, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Deductions Section -->
        <table class="salary-table" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th class="left-align">DEDUCTIONS</th>
                    <th class="right-align">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="label">Tax</td>
                    <td class="amount negative">{{ $currencySymbol }} {{ number_format($tax, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Loan Deduction</td>
                    <td class="amount negative">{{ $currencySymbol }} {{ number_format($loanDeduction, 2) }}</td>
                </tr>
                @if($totalDeductions > 0)
                    @foreach($salary->deductions_detail as $deduction)
                    <tr>
                        <td class="label">{{ $deduction['name'] ?? 'Late/Absent' }}</td>
                        <td class="amount negative">{{ $currencySymbol }} {{ number_format($deduction['amount'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                @else
                <tr>
                    <td class="label">Late/Absent</td>
                    <td class="amount negative">{{ $currencySymbol }}0.00</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Other Deduction</td>
                    <td class="amount negative">{{ $currencySymbol }} {{ number_format($otherDeduction, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label bold">Total Deductions</td>
                    <td class="amount negative bold">{{ $currencySymbol }} {{ number_format($totalDeductions, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Net Salary -->
        <div class="net-salary-section">
            <table class="net-salary-table">
                <tr>
                    <td class="label bold">NET SALARY</td>
                    <td class="amount bold">{{ $currencySymbol }} {{ number_format($salary->net_salary, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- Attendance Summary -->
        @if($salary->attendance_days)
        <div class="attendance-section">
            <div class="attendance-title">Attendance Summary</div>
            <div class="attendance-info">
                Total Days Present: <strong>{{ $salary->attendance_days }} days</strong><br>
                Month: {{ \Carbon\Carbon::createFromFormat('Y-m', $salary->month)->format('F Y') }}
            </div>
        </div>
        @endif

        <!-- Notes -->
        @if($salary->notes)
        <div class="notes">
            <strong>Notes:</strong> {{ $salary->notes }}
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-company">{{ $companyName }}</div>
            <div class="footer-details">
                {!! $companyAddress !!}<br>
                @if($companyEmail)
                    {{ $companyEmail }}<br>
                @endif
                @if($companyPhone)
                    {{ $companyPhone }}
                @endif
            </div>
            @if($companyWebsite)
            <div class="footer-website">{{ $companyWebsite }}</div>
            @endif
            @if($companyRegNo || $companyVat)
            <div class="footer-legal">
                @if($companyRegNo)
                    Company No: {{ $companyRegNo }}
                @endif
                @if($companyRegNo && $companyVat)
                    | 
                @endif
                @if($companyVat)
                    VAT Registration No: {{ $companyVat }}
                @endif
            </div>
            @endif
            <p style="margin-top: 8px; font-size: 8px; color: #888;">
                This is a computer-generated document and does not require a signature.
            </p>
        </div>
    </div>
</body>
</html>
