<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment assigned to you</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.55; color: #1f2937; max-width: 680px; margin: 0 auto; padding: 20px; background-color: #f3f4f6; }
        .email-container { background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #0f766e; color: white; padding: 24px 28px; }
        .header img { max-height: 48px; width: auto; margin-bottom: 10px; display: block; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 700; }
        .content { padding: 28px; }
        .panel { border: 1px solid #d1fae5; background: #ecfdf5; border-radius: 8px; padding: 18px; margin: 18px 0; }
        .panel h2 { margin: 0 0 12px; font-size: 16px; color: #065f46; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 8px 0; vertical-align: top; border-bottom: 1px solid #d1fae5; }
        .grid tr:last-child td { border-bottom: 0; }
        .label { color: #64748b; width: 145px; font-size: 13px; }
        .value { color: #111827; font-weight: 600; }
        .chips { margin-top: 6px; }
        .chip { display: inline-block; margin: 0 6px 6px 0; padding: 5px 9px; border-radius: 999px; background: #ccfbf1; color: #115e59; font-size: 12px; font-weight: 700; }
        .notes-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-top: 18px; }
        .cta { display: inline-block; margin-top: 20px; padding: 12px 22px; background: #0f766e; color: white !important; text-decoration: none; border-radius: 6px; font-weight: 700; }
        .footer { background-color: #f8fafc; padding: 18px 28px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
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
            <p>An appointment has been booked and you are assigned to attend. The sales context is below so you know who you are visiting and what the opportunity is.</p>

            <div class="panel">
                <h2>Appointment and sales context</h2>
                <table class="grid" role="presentation">
                    <tr>
                        <td class="label">Date / time</td>
                        <td class="value">{{ $appointmentDate }} at {{ $appointmentTime }}</td>
                    </tr>
                    <tr>
                        <td class="label">Business</td>
                        <td class="value">{{ $businessName ?: ($customer->business_name ?? null) ?: 'Not provided' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Contact</td>
                        <td class="value">{{ $customer->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Phone</td>
                        <td class="value">{{ $customer->phone ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Email</td>
                        <td class="value">{{ $customer->email ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Address</td>
                        <td class="value">{{ $addressLine ?: 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">What to sell</td>
                        <td class="value">
                            @if(!empty($productNames))
                                <div class="chips">
                                    @foreach($productNames as $productName)
                                        <span class="chip">{{ $productName }}</span>
                                    @endforeach
                                </div>
                            @else
                                Products not selected. Check the lead before attending.
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Lead</td>
                        <td class="value">
                            #{{ $lead->id ?? 'N/A' }}
                            @if($leadStage)
                                - {{ ucwords(str_replace('_', ' ', $leadStage)) }}
                            @endif
                            @if($leadSource)
                                - Source: {{ $leadSource }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Pipeline value</td>
                        <td class="value">GBP {{ number_format((float) ($leadValue ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Created by</td>
                        <td class="value">{{ $createdByName ?: 'Unknown' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Lead owner</td>
                        <td class="value">{{ $leadOwnerName ?: 'Unassigned' }}</td>
                    </tr>
                </table>
            </div>

            @if($notes)
                <div class="notes-box">
                    <strong>Appointment notes</strong><br>
                    {{ $notes }}
                </div>
            @endif

            <p>After the visit, update the appointment status, add outcome notes, and mark products won or lost from the CRM.</p>
            <a href="{{ $appointmentUrl }}" class="cta">Open appointment</a>
        </div>
        <div class="footer">
            <strong>{{ $companyName }}</strong>
            <p style="margin-top: 8px; font-size: 12px; color: #94a3b8;">This is an automated notification.</p>
        </div>
    </div>
</body>
</html>
