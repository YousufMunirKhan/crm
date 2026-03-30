<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket status updated</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .email-container { background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%); color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .content { padding: 24px; }
        .status-change { font-size: 16px; margin: 16px 0; padding: 14px; background: #eef2ff; border-radius: 8px; border-left: 4px solid #6366f1; }
        .detail { margin-bottom: 8px; font-size: 14px; }
        .detail .label { color: #64748b; display: inline-block; min-width: 120px; }
        .cta { display: inline-block; margin-top: 20px; padding: 12px 22px; background: #4f46e5; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { background-color: #f8fafc; padding: 16px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height: 40px; margin-bottom: 12px;">
            @endif
            <h1>Ticket status updated</h1>
        </div>
        <div class="content">
            <p><strong>{{ $changedByName }}</strong> updated the status of ticket <strong>{{ $ticket->ticket_number }}</strong>.</p>
            <div class="status-change">
                <strong>{{ $previousStatusLabel }}</strong> → <strong>{{ $newStatusLabel }}</strong>
            </div>
            <div class="detail"><span class="label">Subject</span>{{ $ticket->subject }}</div>
            @if($ticket->reference_url)
                <div class="detail"><span class="label">Reference link</span><a href="{{ $ticket->reference_url }}">{{ $ticket->reference_url }}</a></div>
            @endif
            @if($ticket->attachments && $ticket->attachments->isNotEmpty())
                <p style="margin-top: 12px; font-size: 13px; color: #64748b;"><strong>Attachments:</strong></p>
                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                    @foreach($ticket->attachments as $att)
                        <li><a href="{{ $att->url }}">{{ $att->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ $ticketUrl }}" class="cta">Open ticket</a>
            <p style="margin-top: 16px; font-size: 12px; color: #64748b; word-break: break-all; line-height: 1.5;">
                <strong>Direct link:</strong><br>
                <a href="{{ $ticketUrl }}" style="color: #4f46e5;">{{ $ticketUrl }}</a>
            </p>
        </div>
        <div class="footer">
            <p style="font-weight: 600; color: #1e293b; margin: 0;">{{ $companyName }}</p>
        </div>
    </div>
</body>
</html>
