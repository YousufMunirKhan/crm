<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employment Contract</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 40px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 200px;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            border-top: 1px solid #000;
            padding-top: 10px;
            text-align: center;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>EMPLOYMENT CONTRACT</h1>
        <p>{{ $company_name }}</p>
    </div>

    <div class="section">
        <div class="section-title">1. PARTIES</div>
        <p>This Employment Contract ("Contract") is entered into on {{ $contract_date }} between:</p>
        <p><strong>{{ $company_name }}</strong> (the "Employer")</p>
        <p>and</p>
        <p><strong>{{ $employee_name }}</strong> (the "Employee")</p>
    </div>

    <div class="section">
        <div class="section-title">2. EMPLOYEE INFORMATION</div>
        <div class="info-row">
            <span class="label">Full Name:</span>
            <span>{{ $employee_name }}</span>
        </div>
        <div class="info-row">
            <span class="label">Email:</span>
            <span>{{ $employee_email }}</span>
        </div>
        <div class="info-row">
            <span class="label">Phone:</span>
            <span>{{ $employee_phone }}</span>
        </div>
        <div class="info-row">
            <span class="label">Address:</span>
            <span>{{ $employee_address }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">3. POSITION AND DUTIES</div>
        <p>The Employee agrees to serve as <strong>{{ $position }}</strong> ({{ $employee_type }}) and shall perform all duties and responsibilities assigned by the Employer.</p>
    </div>

    <div class="section">
        <div class="section-title">4. START DATE</div>
        <p>The employment shall commence on <strong>{{ $hire_date }}</strong>.</p>
    </div>

    <div class="section">
        <div class="section-title">5. TERMS AND CONDITIONS</div>
        <p>The Employee agrees to:</p>
        <ul>
            <li>Perform all duties diligently and to the best of their ability</li>
            <li>Comply with all company policies and procedures</li>
            <li>Maintain confidentiality of company information</li>
            <li>Work during standard business hours or as otherwise agreed</li>
        </ul>
    </div>

    <div class="section">
        <div class="section-title">6. GENERAL PROVISIONS</div>
        <p>This Contract constitutes the entire agreement between the parties and supersedes all prior agreements. Any modifications must be made in writing and signed by both parties.</p>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <p><strong>Employee Signature</strong></p>
            <p style="margin-top: 50px;">_________________________</p>
            <p>{{ $employee_name }}</p>
            <p>Date: _______________</p>
        </div>
        <div class="signature-box">
            <p><strong>Employer Signature</strong></p>
            <p style="margin-top: 50px;">_________________________</p>
            <p>{{ $company_name }}</p>
            <p>Date: _______________</p>
        </div>
    </div>

    <div class="footer">
        <p>This contract was generated on {{ $contract_date }}. Please sign and return a copy to complete the employment process.</p>
    </div>
</body>
</html>

