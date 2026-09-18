@extends('emails.automated.layout')

@section('title', 'Daily lead summary')

@section('preview')
    {{ $teamCount }} of {{ $teamTarget }} leads on {{ $dateLabel }}{{ $teamShort > 0 ? ' — '.$teamShort.' short' : '' }}
@endsection

@section('heading')
    Leads on {{ $dateLabel }}
@endsection

@section('body')
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 22px;">
        <tr>
            <td style="background-color:{{ $teamShort > 0 ? '#fff7ed' : '#f0fdf4' }}; border-left:4px solid {{ $teamShort > 0 ? '#ea580c' : '#16a34a' }}; border-radius:10px; padding:16px 18px;">
                <div style="font-size:30px; line-height:1.1; font-weight:700; color:#0f172a;">
                    {{ $teamCount }}<span style="font-size:17px; font-weight:500; color:#94a3b8;"> / {{ $teamTarget }}</span>
                </div>
                <div style="font-size:14px; color:{{ $teamShort > 0 ? '#7c2d12' : '#14532d' }}; margin-top:4px;">
                    @if($teamShort > 0)
                        The lead target was not met. {{ $teamShort }} short across
                        {{ count($rows) }} {{ count($rows) === 1 ? 'person' : 'people' }}.
                    @else
                        Lead target met across {{ count($rows) }} {{ count($rows) === 1 ? 'person' : 'people' }}.
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Worst shortfall first: this exists to say who needs a word, not to
         read out a register. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
        <tr>
            <th align="left" style="font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; padding:0 8px 8px 0; font-weight:700;">Person</th>
            <th align="left" style="font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; padding:0 8px 8px 0; font-weight:700; width:46%;">Leads today</th>
            <th align="right" style="font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; padding:0 0 8px 8px; font-weight:700;">Short</th>
        </tr>
        @foreach($rows as $row)
            @php($pct = $row['target'] > 0 ? (int) round(min(1, $row['count'] / $row['target']) * 100) : 0)
            @php($met = $row['count'] >= $row['target'])
            <tr>
                <td style="padding:12px 8px 12px 0; border-top:1px solid #e2e8f0; font-size:14px; vertical-align:middle;">
                    <div style="color:#0f172a; font-weight:600;">{{ $row['name'] }}</div>
                    @if($row['role'])
                        <div style="color:#94a3b8; font-size:12px;">{{ $row['role'] }}</div>
                    @endif
                </td>
                <td style="padding:12px 8px 12px 0; border-top:1px solid #e2e8f0; vertical-align:middle;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#e2e8f0; border-radius:5px;">
                        <tr>
                            @if($pct > 0)
                                <td style="width:{{ $pct }}%; background-color:{{ $met ? '#16a34a' : '#2563eb' }}; height:15px; border-radius:5px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                            @if($pct < 100)
                                <td style="width:{{ 100 - $pct }}%; height:15px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                        </tr>
                    </table>
                    <div style="font-size:12px; color:#64748b; margin-top:5px;">{{ $row['count'] }} of {{ $row['target'] }}</div>
                </td>
                <td align="right" style="padding:12px 0 12px 8px; border-top:1px solid #e2e8f0; font-size:16px; font-weight:700; vertical-align:middle; color:{{ $met ? '#15803d' : ($row['short'] >= $row['target'] ? '#b91c1c' : '#b45309') }};">
                    {{ $met ? '—' : $row['short'] }}
                </td>
            </tr>
        @endforeach
    </table>

    @if($nobody)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:18px 0 0;">
            <tr>
                <td style="background-color:#fef2f2; border-left:4px solid #dc2626; border-radius:10px; padding:14px 16px; font-size:14px; color:#991b1b;">
                    Nobody put a lead on the board on {{ $dateLabel }}.
                </td>
            </tr>
        </table>
    @endif

    <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; font-weight:600; margin:28px 0 10px;">
        The team's last {{ count($days) }} working days
    </div>

    @include('emails.automated.partials.day-chart', ['days' => $days])

    @if($salesRows)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:28px 0 0;">
            <tr>
                <td style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:20px;">
                    <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#1d4ed8; font-weight:700;">
                        Month end — {{ $daysLeft }} {{ $daysLeft === 1 ? 'day' : 'days' }} left in {{ $monthLabel }}
                    </div>
                    <div style="font-size:17px; font-weight:700; color:#0f172a; margin:8px 0 14px;">
                        {{ $salesAchieved }} of {{ $salesTarget }} sales achieved{{ $salesShort > 0 ? ', '.$salesShort.' to go' : '' }}.
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                        @foreach($salesRows as $row)
                            @php($pct = $row['target'] > 0 ? (int) round(min(1, $row['achieved'] / $row['target']) * 100) : 0)
                            <tr>
                                <td style="padding:8px 10px 8px 0; font-size:14px; color:#0f172a; vertical-align:middle; white-space:nowrap;">
                                    {{ $row['name'] }}
                                </td>
                                <td style="padding:8px 10px 8px 0; vertical-align:middle; width:55%;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#dbeafe; border-radius:5px;">
                                        <tr>
                                            @if($pct > 0)
                                                <td style="width:{{ $pct }}%; background-color:{{ $row['short'] > 0 ? '#2563eb' : '#16a34a' }}; height:14px; border-radius:5px; font-size:1px; line-height:1px;">&nbsp;</td>
                                            @endif
                                            @if($pct < 100)
                                                <td style="width:{{ 100 - $pct }}%; height:14px; font-size:1px; line-height:1px;">&nbsp;</td>
                                            @endif
                                        </tr>
                                    </table>
                                </td>
                                <td align="right" style="padding:8px 0; font-size:13px; color:#1e40af; vertical-align:middle; white-space:nowrap;">
                                    {{ $row['achieved'] }} / {{ $row['target'] }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endif
@endsection

@section('footnote', 'Sent every working day at 10am UK time. Sundays excluded.')
