<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f1f5f9;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: white;
            padding: 32px 30px;
            text-align: center;
        }
        .header img {
            max-height: 60px;
            width: auto;
            margin-bottom: 16px;
            display: inline-block;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .header .tagline {
            margin-top: 6px;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 32px 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #334155;
            margin-bottom: 20px;
            line-height: 1.7;
        }
        .invoice-box {
            background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
            border-left: 4px solid #0d9488;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
        }
        .invoice-box p {
            margin: 0;
            font-size: 15px;
            color: #0f766e;
        }
        .invoice-box strong {
            font-size: 17px;
            color: #0d9488;
        }
        .attachment-note {
            font-size: 14px;
            color: #64748b;
            margin-top: 24px;
            padding: 16px;
            background-color: #f8fafc;
            border-radius: 8px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 28px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer .company-name {
            font-weight: 600;
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .footer .contact-info {
            font-size: 14px;
            color: #64748b;
            margin: 4px 0;
        }
        .footer .contact-info a {
            color: #0d9488;
            text-decoration: none;
        }
        .social-links {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 12px;
            color: #64748b;
            text-decoration: none;
        }
        .social-links a:hover {
            color: #0d9488;
        }
        .social-links svg {
            width: 28px;
            height: 28px;
            vertical-align: middle;
        }
        .footer-note {
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if(($logoPath ?? null) && file_exists($logoPath))
                <img src="{{ $message->embed($logoPath) }}" alt="{{ $companyName }}">
            @elseif($logoUrl ?? false)
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @endif
            <h1>{{ $companyName }}</h1>
        </div>

        <div class="content">
            <p class="greeting">Hi {{ $customerName }},</p>

            <p class="message">{{ $customMessage }}</p>

            <div class="invoice-box">
                <p>Invoice <strong>{{ $invoice->invoice_number }}</strong> is attached as a PDF.</p>
            </div>

            <p class="attachment-note">
                📎 The invoice document is attached to this email. You can open it directly or save it for your records.
            </p>
        </div>

        <div class="footer">
            <p class="company-name">{{ $companyName }}</p>
            @if($companyAddress)
                <p class="contact-info">📍 {{ $companyAddress }}</p>
            @endif
            @if($companyPhone)
                <p class="contact-info">📞 {{ $companyPhone }}</p>
            @endif
            @if($companyWebsite)
                <p class="contact-info"><a href="{{ $companyWebsite }}" target="_blank" rel="noopener">🌐 {{ $companyWebsite }}</a></p>
            @endif

            @if(!empty($socialFacebook) || !empty($socialTwitter) || !empty($socialLinkedIn) || !empty($socialInstagram) || !empty($socialTikTok))
            <div class="social-links">
                @if(!empty($socialFacebook))
                    <a href="{{ $socialFacebook }}" target="_blank" rel="noopener" title="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                @endif
                @if(!empty($socialTwitter))
                    <a href="{{ $socialTwitter }}" target="_blank" rel="noopener" title="Twitter / X"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                @endif
                @if(!empty($socialLinkedIn))
                    <a href="{{ $socialLinkedIn }}" target="_blank" rel="noopener" title="LinkedIn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                @endif
                @if(!empty($socialInstagram))
                    <a href="{{ $socialInstagram }}" target="_blank" rel="noopener" title="Instagram"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849 1.664-3.26 3.77-4.771 6.919-4.919 1.266-.058 1.644-.07 4.849-.07zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
                @endif
                @if(!empty($socialTikTok))
                    <a href="{{ $socialTikTok }}" target="_blank" rel="noopener" title="TikTok"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
                @endif
            </div>
            @endif

            <p class="footer-note">Thank you for your business! This is an automated message.</p>
        </div>
    </div>
</body>
</html>
