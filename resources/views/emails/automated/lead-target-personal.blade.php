@extends('emails.automated.layout')

@section('title', 'Your leads today')

@section('heading')Where you are today@endsection

@section('body')
    <p>Hello {{ $repName }},</p>

    @if($short <= 0)
        <p>
            You put <strong>{{ $count }}</strong> {{ $count === 1 ? 'lead' : 'leads' }} on the board
            on {{ $dateLabel }} against a target of {{ $target }}. That is the day done.
        </p>
    @else
        <p>
            The daily target is <strong>{{ $target }}</strong> leads. On {{ $dateLabel }} you put on
            <strong>{{ $count }}</strong>, so you are <strong>{{ $short }}</strong> short.
        </p>
    @endif

    {{-- The one number, large enough to be the whole message on a phone. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:18px 0;">
        <tr>
            <td style="background-color:{{ $short <= 0 ? '#f0fdf4' : '#f1f5f9' }}; border-left:4px solid {{ $short <= 0 ? '#16a34a' : '#2563eb' }}; border-radius:8px; padding:18px 20px;">
                <div style="font-size:34px; line-height:1.1; font-weight:700; color:{{ $short <= 0 ? '#15803d' : '#0f172a' }};">
                    {{ $count }} <span style="font-size:18px; font-weight:500; color:#64748b;">of {{ $target }}</span>
                </div>
                <div style="font-size:14px; color:#475569; margin-top:6px;">
                    @if($short <= 0)
                        Target met.
                    @else
                        {{ $short }} more {{ $short === 1 ? 'lead' : 'leads' }} to hit it.
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:8px;">
        Your last {{ count($days) }} working days
    </p>

    @include('emails.automated.partials.day-chart', ['days' => $days])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin-top:20px;">
        <tr>
            <td style="background-color:#f8fafc; border-radius:8px; padding:14px 16px; font-size:14px; color:#334155;">
                <strong>{{ $weekTotal }}</strong> {{ $weekTotal === 1 ? 'lead' : 'leads' }} over those days,
                against <strong>{{ $weekTarget }}</strong> expected.
            </td>
        </tr>
    </table>
@endsection

@section('footnote', 'Automated message from your CRM — sent every working day at 10am UK time.')
