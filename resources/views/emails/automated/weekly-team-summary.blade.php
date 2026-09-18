@extends('emails.automated.layout')

@section('title', 'The team this week')

@section('preview')
    {{ $teamLeads }} of {{ $teamLeadTarget }} leads, {{ $teamSalesWeek }} sales — {{ $weekLabel }}
@endsection

@section('heading')
    The week in full — {{ $weekLabel }}
@endsection

@section('body')
    <p style="margin:0 0 20px;">
        Monday to Saturday, everybody on a target, in the order of who has the most ground to make up.
        A single quiet day says nothing; a week says where to put the next conversation.
    </p>

    {{-- Two numbers first, so the shape of the week is clear before the detail. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
        <tr>
            <td width="50%" style="padding-right:6px; vertical-align:top;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="background-color:{{ $teamLeads >= $teamLeadTarget ? '#f0fdf4' : '#fff7ed' }}; border:1px solid {{ $teamLeads >= $teamLeadTarget ? '#bbf7d0' : '#fed7aa' }}; border-radius:10px; padding:16px 18px;">
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.07em; color:#64748b; font-weight:700;">Leads</div>
                            <div style="font-size:32px; line-height:1.1; font-weight:700; color:#0f172a; margin-top:4px;">
                                {{ $teamLeads }}<span style="font-size:17px; font-weight:500; color:#94a3b8;"> / {{ $teamLeadTarget }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="50%" style="padding-left:6px; vertical-align:top;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:16px 18px;">
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.07em; color:#64748b; font-weight:700;">Sales this week</div>
                            <div style="font-size:32px; line-height:1.1; font-weight:700; color:#0f172a; margin-top:4px;">
                                {{ $teamSalesWeek }}
                            </div>
                            <div style="font-size:12px; color:#1e40af; margin-top:2px;">
                                {{ $teamSalesMonth }} of {{ $teamSalesTarget }} in {{ $monthLabel }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; font-weight:700; margin-bottom:10px;">
        Leads across the week
    </div>

    @include('emails.automated.partials.day-chart', ['days' => $days])

    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; font-weight:700; margin:30px 0 10px;">
        Everybody, furthest behind first
    </div>

    @foreach($rows as $row)
        @php($pct = $row['lead_target'] > 0 ? (int) round(min(1, $row['leads'] / $row['lead_target']) * 100) : 0)
        @php($met = $row['leads'] >= $row['lead_target'])
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 12px;">
            <tr>
                <td style="border:1px solid #e2e8f0; border-left:4px solid {{ $met ? '#16a34a' : ($row['lead_short'] >= $row['lead_target'] ? '#dc2626' : '#ea580c') }}; border-radius:10px; padding:16px 18px;">

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="vertical-align:top;">
                                <div style="font-size:16px; font-weight:700; color:#0f172a;">{{ $row['name'] }}</div>
                                @if($row['role'])
                                    <div style="font-size:12px; color:#94a3b8;">{{ $row['role'] }}</div>
                                @endif
                            </td>
                            <td align="right" style="vertical-align:top; white-space:nowrap;">
                                <div style="font-size:20px; font-weight:700; color:{{ $met ? '#15803d' : '#9a3412' }};">
                                    {{ $row['leads'] }}<span style="font-size:14px; font-weight:500; color:#94a3b8;"> / {{ $row['lead_target'] }}</span>
                                </div>
                                <div style="font-size:12px; color:#64748b;">leads this week</div>
                            </td>
                        </tr>
                    </table>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px; background-color:#e2e8f0; border-radius:5px;">
                        <tr>
                            @if($pct > 0)
                                <td style="width:{{ $pct }}%; background-color:{{ $met ? '#16a34a' : '#2563eb' }}; height:14px; border-radius:5px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                            @if($pct < 100)
                                <td style="width:{{ 100 - $pct }}%; height:14px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                        </tr>
                    </table>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px;">
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; width:130px; vertical-align:top;">Short by</td>
                            <td style="font-size:14px; color:{{ $met ? '#15803d' : '#9a3412' }}; font-weight:600; padding:3px 0;">
                                {{ $met ? 'nothing — target met' : $row['lead_short'].' leads' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; vertical-align:top;">Sales this week</td>
                            <td style="font-size:14px; color:#0f172a; padding:3px 0;">{{ $row['sales_week'] }}</td>
                        </tr>
                        @if($row['sales_target'] > 0)
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; vertical-align:top;">{{ $monthLabel }} sales</td>
                                <td style="font-size:14px; color:#0f172a; padding:3px 0;">
                                    {{ $row['sales_month'] }} of {{ $row['sales_target'] }}{{ $row['sales_short'] > 0 ? ' — '.$row['sales_short'].' to go' : ' — target met' }}
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:3px 12px 3px 0; vertical-align:top;">Last sale</td>
                            <td style="font-size:14px; color:{{ $row['last_sale'] && $row['last_sale']['days_ago'] > 21 ? '#b91c1c' : '#0f172a' }}; padding:3px 0;">
                                @if($row['last_sale'])
                                    {{ $row['last_sale']['at']->format('j M Y') }}
                                    ({{ $row['last_sale']['days_ago'] === 0 ? 'today' : ($row['last_sale']['days_ago'] === 1 ? 'yesterday' : $row['last_sale']['days_ago'].' days ago') }})
                                @else
                                    nothing closed yet
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach
@endsection

@section('footnote', 'Sent every Sunday morning at 10am UK time.')
