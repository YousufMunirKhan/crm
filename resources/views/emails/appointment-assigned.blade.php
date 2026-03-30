<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment assigned to you</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .email-container { background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: white; padding: 28px; text-align: center; }
        .header img { max-height: 48px; width: auto; margin-bottom: 10px; display: inline-block; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .content { padding: 28px; }
        .highlight { background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%); border-left: 4px solid #0d9488; border-radius: 8px; padding: 18px; margin: 20px 0; }
        .highlight strong { color: #0f766e; }
        .detail { display: flex; margin-bottom: 10px; }
        .detail .label { color: #64748b; min-width: 100px; }
        .detail .value { color: #1e293b; font-weight: 600; }
        .notes-box { background-color: #f8fafc; border-radius: 8px; padding: 14px; margin-top: 18px; }
        .cta { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #0d9488; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { background-color: #f8fafc; padding: 20px 28px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 14px; color: #64748b; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @endif
            <h1>Appointment assigned to you</h1>
        </div>
        <div class="content">
            <p>Hello {{ $activity->assignee->name ?? 'Team Member' }},</p>
            <p>An appointment has been booked and <strong>you are assigned to attend</strong>. Please be there at the scheduled time.</p>
            <div class="highlight">
                <strong>Appointment details</strong>
                <div class="detail"><span class="label">Date:</span><span class="value">{{ $appointmentDate }}</span></div>
                <div class="detail"><span class="label">Time:</span><span class="value">{{ $appointmentTime }}</span></div>
                <div class="detail"><span class="label">Customer:</span><span class="value">{{ $customer->name ?? 'N/A' }}</span></div>
                <div class="detail"><span class="label">Phone:</span><span class="value">{{ $customer->phone ?? 'N/A' }}</span></div>
                <div class="detail"><span class="label">Email:</span><span class="value">{{ $customer->email ?? 'N/A' }}</span></div>
                <div class="detail">
                    <span class="label">Address:</span>
                    <span class="value">
                        @php
                            $customerAddressParts = array_filter([
                                $customer->address ?? null,
                                $customer->city ?? null,
                                $customer->postcode ?? null,
                            ]);
                        @endphp
                        {{ $customerAddressParts ? implode(', ', $customerAddressParts) : 'N/A' }}
                    </span>
                </div>
                <div class="detail"><span class="label">Lead #:</span><span class="value">{{ $lead->id }}</span></div>
            </div>
            @if($notes)
            <div class="notes-box">
                <strong>Notes:</strong><br>{{ $notes }}
            </div>
            @endif
            <p>You can update the appointment time, add outcome notes, or mark status (completed / won / lost) from the CRM.</p>
            <a href="{{ $appointmentUrl }}" class="cta">View & update appointment</a>
        </div>
        <div class="footer">
            <p class="company-name" style="font-weight: 600; color: #1e293b;">{{ $companyName }}</p>
            <p style="margin-top: 8px; font-size: 12px; color: #94a3b8;">This is an automated notification.</p>
        </div>
    </div>
</body>
</html>
