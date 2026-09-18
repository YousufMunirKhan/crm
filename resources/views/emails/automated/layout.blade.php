{{--
    The shell every automated email shares.

    The rest of resources/views/emails repeats this stylesheet in full in each
    file, which is why they have drifted apart. These are sent by a schedule
    nobody is watching, so they are the ones that most need to look the same.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .email-container { background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .content { padding: 24px; }
        .highlight { background: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .highlight.warn { background: #fffbeb; border-left-color: #d97706; }
        .detail { margin-bottom: 10px; font-size: 14px; }
        .detail .label { color: #64748b; display: inline-block; min-width: 140px; }
        .detail .value { color: #0f172a; font-weight: 500; }
        .row { padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .row:last-child { border-bottom: 0; }
        .row .who { color: #0f172a; font-weight: 600; }
        .row .meta { color: #64748b; font-size: 13px; }
        .row .late { color: #b91c1c; font-weight: 600; }
        .footer { background-color: #f8fafc; padding: 16px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>@yield('heading')</h1>
        </div>
        <div class="content">
            @yield('body')
        </div>
        <div class="footer">
            <p style="font-weight: 600; color: #1e293b; margin: 0;">{{ $companyName }}</p>
            <p style="margin: 8px 0 0; font-size: 12px;">@yield('footnote', 'Automated message — please reply to this email if anything is wrong.')</p>
        </div>
    </div>
</body>
</html>
