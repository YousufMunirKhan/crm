@extends('emails.automated.layout')

@section('title', 'Invoice due soon')

@section('preview')
    {{ $invoiceNumber }} — {{ $amountDue }} due {{ $dueDate }}
@endsection

@section('heading')
    Invoice {{ $invoiceNumber }}
@endsection

@section('body')
    <p style="margin:0 0 16px;">Hello {{ $customerName }},</p>
    <p style="margin:0 0 18px;">
        A friendly heads-up that invoice <strong>{{ $invoiceNumber }}</strong> falls due in
        {{ $daysUntilDue }} {{ $daysUntilDue === 1 ? 'day' : 'days' }}. We have attached a full copy
        so everything is in one place.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #2563eb; border-radius:10px; padding:18px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; width:120px; vertical-align:top;">Invoice</td>
                        <td style="font-size:15px; color:#0f172a; font-weight:600; padding:4px 0;">{{ $invoiceNumber }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; vertical-align:top;">Due date</td>
                        <td style="font-size:15px; color:#0f172a; padding:4px 0;">{{ $dueDate }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px; color:#64748b; padding:4px 14px 4px 0; vertical-align:top;">Amount due</td>
                        <td style="font-size:19px; color:#0f172a; font-weight:700; padding:4px 0;">{{ $amountDue }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0;">
        If payment is already on its way, please pay this no mind. If anything on the invoice needs
        explaining, reply to this email and we will talk it through.
    </p>
@endsection

@section('footnote', 'You are receiving this because you have an invoice with us.')
