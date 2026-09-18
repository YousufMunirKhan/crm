@extends('emails.automated.layout')

@section('title', 'Daily lead summary')

@section('heading')Leads on {{ $dateLabel }}@endsection

@section('body')
    <p>
        <strong>{{ $teamCount }}</strong> {{ $teamCount === 1 ? 'lead' : 'leads' }} across
        {{ count($rows) }} {{ count($rows) === 1 ? 'person' : 'people' }}, against a target of
        <strong>{{ $teamTarget }}</strong>.
        @if($teamShort > 0)
            The team is <strong>{{ $teamShort }}</strong> short.
        @else
            The team is on target.
        @endif
    </p>

    {{-- Worst shortfall first: the point of this is who needs a word, not a roll call. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:18px 0; border-collapse:collapse;">
        <tr>
            <th align="left" style="font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; padding:0 8px 8px 0; font-weight:600;">Person</th>
            <th align="left" style="font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; padding:0 8px 8px 0; font-weight:600; width:45%;">Against target</th>
            <th align="right" style="font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; padding:0 0 8px 8px; font-weight:600;">Short</th>
        </tr>
        @foreach($rows as $row)
            @php($ratio = $row['target'] > 0 ? min(1, $row['count'] / $row['target']) : 0)
            @php($pct = (int) round($ratio * 100))
            @php($met = $row['count'] >= $row['target'])
            <tr>
                <td style="padding:10px 8px 10px 0; border-top:1px solid #e2e8f0; font-size:14px; vertical-align:middle;">
                    <span style="color:#0f172a; font-weight:600;">{{ $row['name'] }}</span>
                    @if($row['role'])
                        <div style="color:#94a3b8; font-size:12px;">{{ $row['role'] }}</div>
                    @endif
                </td>
                <td style="padding:10px 8px 10px 0; border-top:1px solid #e2e8f0; vertical-align:middle;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#e2e8f0; border-radius:4px;">
                        <tr>
                            @if($pct > 0)
                                <td style="width:{{ $pct }}%; background-color:{{ $met ? '#16a34a' : '#2563eb' }}; height:14px; border-radius:4px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                            @if($pct < 100)
                                <td style="width:{{ 100 - $pct }}%; height:14px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                        </tr>
                    </table>
                    <div style="font-size:12px; color:#64748b; margin-top:4px;">{{ $row['count'] }} of {{ $row['target'] }}</div>
                </td>
                <td align="right" style="padding:10px 0 10px 8px; border-top:1px solid #e2e8f0; font-size:15px; font-weight:700; vertical-align:middle; color:{{ $met ? '#15803d' : ($row['short'] >= $row['target'] ? '#b91c1c' : '#b45309') }};">
                    {{ $met ? '—' : $row['short'] }}
                </td>
            </tr>
        @endforeach
    </table>

    <p style="font-size:13px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin:24px 0 8px;">
        The team's last {{ count($days) }} working days
    </p>

    @include('emails.automated.partials.day-chart', ['days' => $days])

    @if($nobody)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin-top:20px;">
            <tr>
                <td style="background-color:#fffbeb; border-left:4px solid #d97706; border-radius:8px; padding:14px 16px; font-size:14px; color:#92400e;">
                    Nobody put a lead on the board on {{ $dateLabel }}.
                </td>
            </tr>
        </table>
    @endif
@endsection

@section('footnote', 'Automated message from your CRM — sent every working day at 10am UK time.')
