@extends('emails.automated.layout')

@section('title', 'Your leads today')

@section('preview')
    {{ $count }} of {{ $target }} leads on {{ $dateLabel }}{{ $short > 0 ? ' — '.$short.' short' : '' }}
@endsection

@section('heading')
    @if($short > 0)
        Lead target not met
    @else
        Lead target met
    @endif
@endsection

@section('body')
    <p style="margin:0 0 16px;">Hello {{ $repName }},</p>

    {{-- The verdict first and in plain words: the number below is the evidence. --}}
    @if($short > 0)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
            <tr>
                <td style="background-color:#fff7ed; border-left:4px solid #ea580c; border-radius:10px; padding:16px 18px;">
                    <div style="font-size:15px; font-weight:700; color:#9a3412;">
                        You did not hit your lead target on {{ $dateLabel }}.
                    </div>
                    <div style="font-size:14px; color:#7c2d12; margin-top:4px;">
                        The target is {{ $target }} leads a day. You put on {{ $count }}, so you are
                        {{ $short }} {{ $short === 1 ? 'lead' : 'leads' }} short.
                    </div>
                </td>
            </tr>
        </table>
    @else
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
            <tr>
                <td style="background-color:#f0fdf4; border-left:4px solid #16a34a; border-radius:10px; padding:16px 18px;">
                    <div style="font-size:15px; font-weight:700; color:#166534;">
                        Target met on {{ $dateLabel }}.
                    </div>
                    <div style="font-size:14px; color:#14532d; margin-top:4px;">
                        {{ $count }} {{ $count === 1 ? 'lead' : 'leads' }} against a target of {{ $target }}.
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:20px;">
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; font-weight:600;">
                    Leads today
                </div>
                <div style="font-size:40px; line-height:1.05; font-weight:700; color:{{ $short > 0 ? '#0f172a' : '#15803d' }}; margin-top:6px;">
                    {{ $count }}<span style="font-size:20px; font-weight:500; color:#94a3b8;"> / {{ $target }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; font-weight:600; margin-bottom:10px;">
        Your last {{ count($days) }} working days
    </div>

    @include('emails.automated.partials.day-chart', ['days' => $days])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:18px 0 0;">
        <tr>
            <td style="background-color:#f8fafc; border-radius:10px; padding:14px 16px; font-size:14px; color:#334155;">
                <strong>{{ $weekTotal }}</strong> {{ $weekTotal === 1 ? 'lead' : 'leads' }} over those days,
                against <strong>{{ $weekTarget }}</strong> expected.
            </td>
        </tr>
    </table>

    {{-- Only near the end of the month, when the monthly figure is something
         that can still be moved and is the thing worth saying. --}}
    @if($sales)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:26px 0 0;">
            <tr>
                <td style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:20px;">
                    <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#1d4ed8; font-weight:700;">
                        {{ $daysLeft }} {{ $daysLeft === 1 ? 'day' : 'days' }} left in {{ $monthLabel }}
                    </div>

                    @if($sales['short'] > 0)
                        <div style="font-size:17px; font-weight:700; color:#0f172a; margin:8px 0 2px;">
                            You have achieved {{ $sales['achieved'] }} of {{ $sales['target'] }} sales.
                        </div>
                        <div style="font-size:15px; color:#1e3a8a;">
                            {{ $sales['short'] }} {{ $sales['short'] === 1 ? 'sale' : 'sales' }} still to go.
                        </div>
                    @else
                        <div style="font-size:17px; font-weight:700; color:#15803d; margin:8px 0 2px;">
                            Monthly sales target met — {{ $sales['achieved'] }} of {{ $sales['target'] }}.
                        </div>
                    @endif

                    @php($salesPct = $sales['target'] > 0 ? (int) round(min(1, $sales['achieved'] / $sales['target']) * 100) : 0)
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                           style="margin-top:14px; background-color:#dbeafe; border-radius:5px;">
                        <tr>
                            @if($salesPct > 0)
                                <td style="width:{{ $salesPct }}%; background-color:{{ $sales['short'] > 0 ? '#2563eb' : '#16a34a' }}; height:16px; border-radius:5px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                            @if($salesPct < 100)
                                <td style="width:{{ 100 - $salesPct }}%; height:16px; font-size:1px; line-height:1px;">&nbsp;</td>
                            @endif
                        </tr>
                    </table>
                    <div style="font-size:12px; color:#1e40af; margin-top:6px;">{{ $salesPct }}% of the month's target</div>
                </td>
            </tr>
        </table>
    @endif
@endsection

@section('footnote', 'Sent every working day at 10am UK time. Sundays excluded.')
