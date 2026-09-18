@extends('emails.automated.layout')

@section('title', 'Your appointments today')

@section('preview')
    {{ count($appointments) }} {{ count($appointments) === 1 ? 'appointment' : 'appointments' }} on {{ $today }}
@endsection

@section('heading')
    Your day — {{ $today }}
@endsection

@section('body')
    <p style="margin:0 0 18px;">
        Morning {{ $repName }}. Here is your day — <strong>{{ count($appointments) }}</strong>
        {{ count($appointments) === 1 ? 'appointment' : 'appointments' }}, in the order you will reach them.
        Everything you need for each one is on the card.
    </p>

    @foreach($appointments as $apt)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 14px;">
            <tr>
                <td style="border:1px solid #e2e8f0; border-left:4px solid #2563eb; border-radius:10px; padding:16px 18px;">

                    <div style="font-size:13px; font-weight:700; color:#1d4ed8; letter-spacing:0.02em;">
                        {{ $apt['time'] }}
                    </div>
                    <div style="font-size:17px; font-weight:700; color:#0f172a; margin-top:2px;">
                        {{ $apt['customer'] }}
                    </div>
                    @if($apt['contact'])
                        <div style="font-size:13px; color:#64748b;">Contact: {{ $apt['contact'] }}</div>
                    @endif

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px;">
                        @if($apt['place'])
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 10px 3px 0; width:92px; vertical-align:top;">Address</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">{{ $apt['place'] }}</td>
                            </tr>
                        @endif
                        @if($apt['phone'])
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 10px 3px 0; vertical-align:top;">Phone</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">{{ $apt['phone'] }}</td>
                            </tr>
                        @endif
                        @if($apt['email'])
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 10px 3px 0; vertical-align:top;">Email</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">{{ $apt['email'] }}</td>
                            </tr>
                        @endif
                        @if($apt['lead_id'])
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 10px 3px 0; vertical-align:top;">Lead</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">
                                    #{{ $apt['lead_id'] }}{{ $apt['stage'] ? ' — '.$apt['stage'] : '' }}
                                </td>
                            </tr>
                        @endif
                        @if($apt['value'] > 0)
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 10px 3px 0; vertical-align:top;">Value</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">GBP {{ number_format($apt['value'], 2) }}</td>
                            </tr>
                        @endif
                    </table>

                    @if($apt['products'])
                        <div style="margin-top:12px;">
                            @foreach($apt['products'] as $product)
                                <span style="display:inline-block; background-color:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;
                                             border-radius:999px; padding:3px 11px; font-size:12px; font-weight:600; margin:0 5px 5px 0;">
                                    {{ $product }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div style="margin-top:12px; font-size:12px; color:#b45309;">No product on this lead.</div>
                    @endif

                    @if($apt['about'])
                        <div style="margin-top:12px; background-color:#f8fafc; border-radius:8px; padding:10px 12px; font-size:13px; color:#334155; white-space:pre-wrap;">{{ $apt['about'] }}</div>
                    @endif

                    @if($apt['url'])
                        <div style="margin-top:14px;">
                            <a href="{{ $apt['url'] }}"
                               style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none;
                                      font-size:13px; font-weight:600; padding:9px 16px; border-radius:8px;">
                                Open appointment
                            </a>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin:16px 0 0; font-size:12px; color:#64748b;">Times are UK time.</p>
@endsection

@section('footnote', 'Sent every morning at 7am UK time.')
