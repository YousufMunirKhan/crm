<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New ticket comment</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .email-container { background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .content { padding: 24px; }
        .meta { font-size: 13px; color: #64748b; margin-bottom: 16px; }
        .comment-box { background: #f0fdfa; border-left: 4px solid #0d9488; border-radius: 8px; padding: 16px; white-space: pre-wrap; font-size: 14px; color: #134e4a; }
        .cta { display: inline-block; margin-top: 20px; padding: 12px 22px; background: #0d9488; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { background-color: #f8fafc; padding: 16px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height: 40px; margin-bottom: 12px;">
            @endif
            <h1>@if($ticketMessage->is_internal) Internal note @else New comment @endif on {{ $ticket->ticket_number }}</h1>
        </div>
        <div class="content">
            <p class="meta">
                <strong>{{ $ticketMessage->user->name ?? 'Someone' }}</strong> commented on
                <strong>{{ $ticket->ticket_number }}</strong> — {{ $ticket->subject }}
            </p>
            <div class="comment-box">{{ $ticketMessage->message }}</div>
            @if($ticket->reference_url)
                <p style="margin-top: 14px; font-size: 13px;"><strong>Reference link:</strong> <a href="{{ $ticket->reference_url }}">{{ $ticket->reference_url }}</a></p>
            @endif
            @if($ticket->attachments && $ticket->attachments->isNotEmpty())
                <p style="margin-top: 8px; font-size: 13px;"><strong>Files on ticket:</strong></p>
                <ul style="margin: 4px 0 0; padding-left: 20px; font-size: 13px;">
                    @foreach($ticket->attachments as $att)
                        <li><a href="{{ $att->url }}">{{ $att->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ $ticketUrl }}" class="cta">View ticket &amp; replies</a>
            <p style="margin-top: 16px; font-size: 12px; color: #64748b; word-break: break-all; line-height: 1.5;">
                <strong>Direct link:</strong><br>
                <a href="{{ $ticketUrl }}" style="color: #0d9488;">{{ $ticketUrl }}</a>
            </p>
        </div>
        <div class="footer">
            <p style="font-weight: 600; color: #1e293b; margin: 0;">{{ $companyName }}</p>
            <p style="margin: 8px 0 0; font-size: 12px;">You are notified because you created or are assigned to this ticket.</p>
        </div>
    </div>
</body>
</html>
