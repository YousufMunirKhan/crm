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
        If it has already been paid, reply with the date it went out and we will match it up and stop these
        reminders. If something is holding it up, tell us and we will work it out with you.
    </p>
@endsection

@section('footnote', 'You are receiving this because you have an unpaid invoice with us.')
