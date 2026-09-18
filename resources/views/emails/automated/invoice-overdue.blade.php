@extends('emails.automated.layout')

@section('title', 'Invoice overdue')

@section('preview')
    {{ $invoiceNumber }} — {{ $amountDue }} outstanding, {{ $daysOverdue }} days past due
@endsection

@section('heading')
    Invoice {{ $invoiceNumber }} is overdue
@endsection

@section('body')
    <p style="margin:0 0 16px;">Hello {{ $customerName }},</p>
    <p style="margin:0 0 18px;">
        Invoice <strong>{{ $invoiceNumber }}</strong> passed its due date of {{ $dueDate }} and is still
        showing as unpaid — <strong>{{ $daysOverdue }} {{ $daysOverdue === 1 ? 'day' : 'days' }}</strong> ago now.
        We have attached the invoice again in case it is easier to hand.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:#fff7ed; border:1px solid #fed7aa; border-left:4px solid #ea580c; border-radius:10px; padding:18px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="font-size:13px; color:#9a3412; padding:4px 14px 4px 0; width:120px; vertical-align:top;">Invoice</td>
                        <td style="font-size:15px; color:#0f172a; font-weight:600; padding:4px 0;">{{ $invoiceNumber }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px; color:#9a3412; padding:4px 14px 4px 0; vertical-align:top;">Due date</td>
                        <td style="font-size:15px; color:#0f172a; padding:4px 0;">{{ $dueDate }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px; color:#9a3412; padding:4px 14px 4px 0; vertical-align:top;">Outstanding</td>
                        <td style="font-size:19px; color:#9a3412; font-weight:700; padding:4px 0;">{{ $amountDue }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0;">
        If something is holding it up, reply and tell us — we would far rather work it out with you than
        keep sending these.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:22px 0 0;">
        <tr>
            <td style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px 18px;">
                <div style="font-size:14px; color:#334155; margin-bottom:12px;">
                    Already paid it? Tell us here and we will stop these reminders straight away.
                </div>
                <a href="{{ $paidUrl }}"
                   style="display:inline-block; background-color:#0f172a; color:#ffffff; text-decoration:none;
                          font-size:14px; font-weight:600; padding:11px 20px; border-radius:8px;">
                    I have already paid this
                </a>
            </td>
        </tr>
    </table>

@endsection

@section('footnote', 'You are receiving this because you have an unpaid invoice with us.')
