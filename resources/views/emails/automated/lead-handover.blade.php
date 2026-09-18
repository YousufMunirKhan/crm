@extends('emails.automated.layout')

@section('title', 'Leads handed over to you')

@section('preview')
    {{ $total }} of {{ $fromName }}'s leads are now yours
@endsection

@section('heading')
    {{ $total }} {{ $total === 1 ? 'lead has' : 'leads have' }} come to you
@endsection

@section('body')
    <p style="margin:0 0 16px;">Hello {{ $recipientName }},</p>

    <p style="margin:0 0 18px;">
        {{ $fromName }} is no longer working these, so {{ $total }} of their
        {{ $total === 1 ? 'lead' : 'leads' }} have been moved to you. They are listed below, the closest to
        closing first.
    </p>

    <p style="margin:0 0 20px;">
        Please go through them and <strong>close anything that is not real</strong> — gone quiet, bought
        elsewhere, never a fit. A pipeline full of leads nobody is working tells us nothing, and these have
        had nobody on them for a while.
    </p>

    @if($totalValue > 0)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 22px;">
            <tr>
                <td style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:16px 18px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.07em; color:#1d4ed8; font-weight:700;">
                        Pipeline value handed over
                    </div>
                    <div style="font-size:28px; line-height:1.1; font-weight:700; color:#0f172a; margin-top:4px;">
                        GBP {{ number_format($totalValue, 2) }}
                    </div>
                </td>
            </tr>
        </table>
    @endif

    @foreach($rows as $row)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 12px;">
            <tr>
                <td style="border:1px solid #e2e8f0; border-left:4px solid #2563eb; border-radius:10px; padding:16px 18px;">

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="vertical-align:top;">
                                <div style="font-size:16px; font-weight:700; color:#0f172a;">{{ $row['customer'] }}</div>
                                @if($row['contact'])
                                    <div style="font-size:13px; color:#64748b;">Contact: {{ $row['contact'] }}</div>
                                @endif
                            </td>
                            <td align="right" style="vertical-align:top; white-space:nowrap;">
                                <span style="display:inline-block; background-color:#f1f5f9; color:#334155; border-radius:999px;
                                             padding:3px 11px; font-size:12px; font-weight:600;">
                                    {{ $row['stage'] }}
                                </span>
                            </td>
                        </tr>
                    </table>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px;">
                        @if($row['phone'])
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; width:110px; vertical-align:top;">Phone</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">{{ $row['phone'] }}</td>
                            </tr>
                        @endif
                        @if($row['value'] > 0)
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; vertical-align:top;">Value</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">GBP {{ number_format($row['value'], 2) }}</td>
                            </tr>
                        @endif
                        @if($row['source'])
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; vertical-align:top;">Source</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">{{ $row['source'] }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; vertical-align:top;">Follow-up</td>
                            <td style="font-size:14px; color:{{ $row['follow_up'] ? '#0f172a' : '#b45309' }}; padding:3px 0;">
                                {{ $row['follow_up'] ?: 'none set — decide a date or close it' }}
                            </td>
                        </tr>
                    </table>

                    @if($row['products'])
                        <div style="margin-top:12px;">
                            @foreach($row['products'] as $product)
                                <span style="display:inline-block; background-color:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;
                                             border-radius:999px; padding:3px 11px; font-size:12px; font-weight:600; margin:0 5px 5px 0;">
                                    {{ $product }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <div style="margin-top:14px;">
                        <a href="{{ $row['url'] }}"
                           style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none;
                                  font-size:13px; font-weight:600; padding:9px 16px; border-radius:8px;">
                            Open lead #{{ $row['id'] }}
                        </a>
                    </div>
                </td>
            </tr>
        </table>
    @endforeach
@endsection

@section('footnote', 'Sent once, when the leads were moved.')
