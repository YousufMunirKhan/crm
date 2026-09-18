@extends('emails.automated.layout')

@section('title', 'Your day and your month')

@section('preview')
    {{ $count }} of {{ $target }} leads today{{ $sales && $sales['target'] > 0 ? ', '.$sales['achieved'].' of '.$sales['target'].' sales this month' : '' }}
@endsection

@section('heading')
    @if($short > 0)
        You are {{ $short }} {{ $short === 1 ? 'lead' : 'leads' }} short today
    @else
        Today's lead target is done
    @endif
@endsection

@section('body')
    <p style="margin:0 0 18px;">Morning {{ $repName }},</p>

    {{-- ------------------------------------------------------------ leads --}}
    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; font-weight:700; margin-bottom:10px;">
        Leads &mdash; {{ $dateLabel }}
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:{{ $short > 0 ? '#fff7ed' : '#f0fdf4' }}; border:1px solid {{ $short > 0 ? '#fed7aa' : '#bbf7d0' }}; border-left:4px solid {{ $short > 0 ? '#ea580c' : '#16a34a' }}; border-radius:10px; padding:18px 20px;">
                <div style="font-size:40px; line-height:1.05; font-weight:700; color:{{ $short > 0 ? '#9a3412' : '#15803d' }};">
                    {{ $count }}<span style="font-size:20px; font-weight:500; color:#94a3b8;"> / {{ $target }}</span>
                </div>
                <div style="font-size:15px; color:{{ $short > 0 ? '#7c2d12' : '#14532d' }}; margin-top:6px;">
                    @if($short > 0)
                        The daily target is {{ $target }}. You are
                        <strong>{{ $short }} {{ $short === 1 ? 'lead' : 'leads' }}</strong> short — that is
                        {{ $short === 1 ? 'one conversation' : $short.' conversations' }} away.
                    @else
                        Target met. {{ $count }} {{ $count === 1 ? 'lead' : 'leads' }} on the board today.
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; font-weight:700; margin-bottom:10px;">
        Your last {{ count($days) }} working days
    </div>

    @include('emails.automated.partials.day-chart', ['days' => $days])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0 0;">
        <tr>
            <td style="background-color:#f8fafc; border-radius:10px; padding:13px 16px; font-size:14px; color:#334155;">
                <strong>{{ $weekTotal }}</strong> over those days against <strong>{{ $weekTarget }}</strong> expected.
            </td>
        </tr>
    </table>

    {{-- ------------------------------------------------------------ sales --}}
    @if($sales)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:26px 0 0;">
            <tr>
                <td style="border-top:1px solid #e2e8f0; padding-top:26px;">

                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; font-weight:700; margin-bottom:10px;">
                        Sales &mdash; {{ $monthLabel }} so far
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="background-color:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #2563eb; border-radius:10px; padding:18px 20px;">

                                @if($sales['target'] > 0)
                                    <div style="font-size:40px; line-height:1.05; font-weight:700; color:{{ $sales['short'] > 0 ? '#1e3a8a' : '#15803d' }};">
                                        {{ $sales['achieved'] }}<span style="font-size:20px; font-weight:500; color:#94a3b8;"> / {{ $sales['target'] }}</span>
                                    </div>
                                    <div style="font-size:15px; color:#1e3a8a; margin-top:6px;">
                                        @if($sales['short'] > 0)
                                            You have made <strong>{{ $sales['achieved'] }}</strong>
                                            {{ $sales['achieved'] === 1 ? 'sale' : 'sales' }} this month.
                                            <strong>{{ $sales['short'] }}</strong> to go to reach your target of {{ $sales['target'] }}.
                                        @else
                                            Monthly target met — {{ $sales['achieved'] }} of {{ $sales['target'] }}. Anything now is on top.
                                        @endif
                                    </div>
                                @else
                                    <div style="font-size:40px; line-height:1.05; font-weight:700; color:#1e3a8a;">
                                        {{ $sales['achieved'] }}
                                    </div>
                                    <div style="font-size:15px; color:#1e3a8a; margin-top:6px;">
                                        {{ $sales['achieved'] === 1 ? 'sale' : 'sales' }} closed this month. No monthly sales target is set for you.
                                    </div>
                                @endif

                                <div style="margin-top:16px;">
                                    @include('emails.automated.partials.target-bar', [
                                        'achieved' => $sales['achieved'],
                                        'target' => $sales['target'],
                                    ])
                                </div>

                                {{-- The figure that shows somebody has gone quiet, rather
                                     than merely started the month slowly. --}}
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:16px;">
                                    <tr>
                                        <td style="background-color:#ffffff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 14px;">
                                            @if($lastSale)
                                                <div style="font-size:13px; color:#64748b;">Your last sale</div>
                                                <div style="font-size:15px; color:#0f172a; font-weight:600; margin-top:2px;">
                                                    {{ $lastSale['at']->format('l j F Y') }}
                                                    <span style="font-weight:400; color:#64748b;">
                                                        ({{ $lastSale['days_ago'] === 0 ? 'today' : ($lastSale['days_ago'] === 1 ? 'yesterday' : $lastSale['days_ago'].' days ago') }})
                                                    </span>
                                                </div>
                                                @if($lastSale['customer'])
                                                    <div style="font-size:13px; color:#475569; margin-top:2px;">
                                                        {{ $lastSale['customer'] }}{{ $lastSale['product'] ? ' — '.$lastSale['product'] : '' }}
                                                    </div>
                                                @endif
                                            @else
                                                <div style="font-size:13px; color:#64748b;">Your last sale</div>
                                                <div style="font-size:15px; color:#0f172a; font-weight:600; margin-top:2px;">
                                                    Nothing closed yet.
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                </table>

                                @if($monthEnd && $sales['short'] > 0)
                                    <div style="margin-top:14px; font-size:15px; font-weight:700; color:#9a3412;">
                                        {{ $daysLeft }} {{ $daysLeft === 1 ? 'day' : 'days' }} left in {{ $monthLabel }}.
                                        {{ $sales['short'] }} still to find — worth lining up the closest ones today.
                                    </div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif
@endsection

@section('footnote', 'Sent every working day at 10am UK time. Sundays excluded.')
