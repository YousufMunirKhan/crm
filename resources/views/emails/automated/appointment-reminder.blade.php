@extends('emails.automated.layout')

@section('title', 'Appointment reminder')

@section('preview')
    {{ $when }}
@endsection

@section('heading')
    We are seeing you tomorrow
@endsection

@section('body')
    <p style="margin:0 0 16px;">Hello {{ $customerName }},</p>
    <p style="margin:0 0 18px;">
        Just a note to say we are looking forward to seeing you tomorrow.
        Everything you need is below.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #2563eb; border-radius:10px; padding:18px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; width:120px; vertical-align:top;">When</td>
                        <td style="font-size:15px; color:#0f172a; font-weight:600; padding:4px 0;">{{ $when }}</td>
                    </tr>
                    @if($place)
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; vertical-align:top;">Where</td>
                            <td style="font-size:15px; color:#0f172a; padding:4px 0;">{{ $place }}</td>
                        </tr>
                    @endif
                    @if($repName)
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; vertical-align:top;">Who is coming</td>
                            <td style="font-size:15px; color:#0f172a; padding:4px 0;">{{ $repName }}</td>
                        </tr>
                    @endif
                    @if($repPhone)
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; vertical-align:top;">Their number</td>
                            <td style="font-size:15px; color:#0f172a; padding:4px 0;">{{ $repPhone }}</td>
                        </tr>
                    @endif
                    @if($about)
                        <tr>
                            <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; vertical-align:top;">About</td>
                            <td style="font-size:15px; color:#0f172a; padding:4px 0;">{{ $about }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0;">
        If tomorrow no longer suits, simply reply to this email and we will find a time that does —
        it is no trouble at all.
    </p>
@endsection

@section('footnote', 'You are receiving this because you have an appointment booked with us.')
