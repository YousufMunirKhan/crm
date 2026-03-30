<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employment Contract</title>
</head>
<body>
    <h2>Your Employment Contract</h2>
    
    <p>Dear {{ $user->name }},</p>
    
    <p>Please find attached your employment contract with {{ $company_name }}.</p>
    
    <p>Please review the contract carefully. If you have any questions, please don't hesitate to contact us.</p>
    
    <p>Best regards,<br>
    {{ $company_name }} Team</p>
</body>
</html>

