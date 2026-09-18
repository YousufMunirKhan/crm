@extends('emails.automated.layout')

@section('title', 'Your follow-ups')

@section('preview')
    {{ count($overdue) }} overdue, {{ count($dueToday) }} due today
@endsection

@section('heading')
    Follow-ups waiting on you
@endsection

@section('body')
    <p style="margin:0 0 18px;">
        Morning {{ $repName }}. Here is what is sitting with you — the overdue ones first,
        because those are the ones going cold.
    </p>

    @if(count($overdue))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 22px;">
            <tr>
                <td style="background-color:#fff7ed; border:1px solid #fed7aa; border-left:4px solid #ea580c; border-radius:10px; padding:16px 18px;">
                    <div style="font-size:15px; font-weight:700; color:#9a3412; margin-bottom:4px;">
                        {{ count($overdue) }} overdue
                    </div>
                    <div style="font-size:13px; color:#7c2d12; margin-bottom:10px;">Due before today. Clear these first.</div>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                        @foreach($overdue as $item)
                            <tr>
                                <td style="border-top:1px solid #fed7aa; padding:10px 0;">
                                    <div style="font-size:15px; font-weight:600; color:#0f172a;">{{ $item['customer'] }}</div>
                                    <div style="font-size:13px; color:#9a3412;">
                                        <strong>{{ $item['due'] }}</strong>{{ $item['stage'] ? ' · '.$item['stage'] : '' }}
                                    </div>
                                    @if($item['phone'])
                                        <div style="font-size:13px; color:#64748b;">{{ $item['phone'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @if(count($dueToday))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
            <tr>
                <td style="background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #2563eb; border-radius:10px; padding:16px 18px;">
                    <div style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:10px;">
                        {{ count($dueToday) }} due today
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                        @foreach($dueToday as $item)
                            <tr>
                                <td style="border-top:1px solid #e2e8f0; padding:10px 0;">
                                    <div style="font-size:15px; font-weight:600; color:#0f172a;">{{ $item['customer'] }}</div>
                                    <div style="font-size:13px; color:#64748b;">
                                        {{ $item['due'] }}{{ $item['stage'] ? ' · '.$item['stage'] : '' }}
                                    </div>
                                    @if($item['phone'])
                                        <div style="font-size:13px; color:#64748b;">{{ $item['phone'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0; font-size:12px; color:#64748b;">Dates are UK time.</p>
@endsection

@section('footnote', 'Sent every morning at 7:10am UK time.')
