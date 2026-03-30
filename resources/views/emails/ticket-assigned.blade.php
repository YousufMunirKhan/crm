<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket assigned</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .email-container { background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .content { padding: 24px; }
        .highlight { background: #f1f5f9; border-left: 4px solid #2563eb; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .detail { margin-bottom: 10px; font-size: 14px; }
        .detail .label { color: #64748b; display: inline-block; min-width: 140px; }
        .detail .value { color: #0f172a; font-weight: 500; }
        .desc { background: #f8fafc; border-radius: 8px; padding: 14px; margin-top: 12px; white-space: pre-wrap; font-size: 14px; color: #334155; }
        .cta { display: inline-block; margin-top: 20px; padding: 12px 22px; background: #2563eb; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { background-color: #f8fafc; padding: 16px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height: 40px; margin-bottom: 12px;">
            @endif
            <h1>You’ve been assigned a ticket</h1>
        </div>
        <div class="content">
            <p>Hello {{ $ticket->assignee->name ?? 'there' }},</p>
            <p><strong>{{ $ticket->ticket_number }}</strong> has been assigned to you. Full details are below.</p>

            <div class="highlight">
                <div class="detail"><span class="label">Ticket number</span><span class="value">{{ $ticket->ticket_number }}</span></div>
                <div class="detail"><span class="label">Subject</span><span class="value">{{ $ticket->subject }}</span></div>
                <div class="detail"><span class="label">Priority</span><span class="value">{{ ucfirst($ticket->priority) }}</span></div>
                <div class="detail"><span class="label">Status</span><span class="value">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span></div>
                @if($ticket->estimated_resolve_hours)
                    <div class="detail"><span class="label">Target resolution</span><span class="value">Within {{ $ticket->estimated_resolve_hours }} hour(s)</span></div>
                @endif
                @if($ticket->sla_due_at)
                    <div class="detail"><span class="label">Due by</span><span class="value">{{ $ticket->sla_due_at->format('d M Y, H:i') }}</span></div>
                @endif
                <div class="detail"><span class="label">Customer</span><span class="value">{{ $ticket->customer->name ?? '—' }} @if($ticket->customer && $ticket->customer->phone) ({{ $ticket->customer->phone }}) @endif</span></div>
                <div class="detail"><span class="label">Created by</span><span class="value">{{ $ticket->creator->name ?? '—' }}</span></div>
                <div class="detail"><span class="label">Created at</span><span class="value">{{ $ticket->created_at?->format('d M Y, H:i') }}</span></div>
            </div>

            @if($ticket->reference_url)
                <p style="margin-bottom: 6px; font-size: 13px; color: #64748b;"><strong>Google Drive / Sheet link</strong></p>
                <p style="margin-top: 4px; font-size: 14px;"><a href="{{ $ticket->reference_url }}">{{ $ticket->reference_url }}</a></p>
            @endif

            @if($ticket->description)
                <p style="margin-bottom: 6px; font-size: 13px; color: #64748b;"><strong>Description</strong></p>
                <div class="desc">{{ $ticket->description }}</div>
            @endif

            @if($ticket->attachments && $ticket->attachments->isNotEmpty())
                <p style="margin-bottom: 6px; font-size: 13px; color: #64748b;"><strong>Attached files</strong></p>
                <ul style="margin: 0 0 16px; padding-left: 20px; font-size: 14px;">
                    @foreach($ticket->attachments as $att)
                        <li><a href="{{ $att->url }}">{{ $att->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif

            <a href="{{ $ticketUrl }}" class="cta">Open ticket in CRM</a>
            <p style="margin-top: 16px; font-size: 12px; color: #64748b; word-break: break-all; line-height: 1.5;">
                <strong>Direct link:</strong><br>
                <a href="{{ $ticketUrl }}" style="color: #2563eb;">{{ $ticketUrl }}</a>
            </p>
        </div>
        <div class="footer">
            <p style="font-weight: 600; color: #1e293b; margin: 0;">{{ $companyName }}</p>
            <p style="margin: 8px 0 0; font-size: 12px;">Automated message — reply in the CRM ticket thread.</p>
        </div>
    </div>
</body>
</html>
