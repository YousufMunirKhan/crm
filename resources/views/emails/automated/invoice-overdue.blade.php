@extends('emails.automated.layout')

@section('title', 'Invoice overdue')
@section('heading')
    Invoice {{ $invoiceNumber }} is overdue
@endsection

@section('body')
    <p>Hello {{ $customerName }},</p>
    <p>
        Invoice <strong>{{ $invoiceNumber }}</strong> was due on {{ $dueDate }} and is now
        <strong>{{ $daysOverdue }} {{ $daysOverdue === 1 ? 'day' : 'days' }}</strong> past its date. A copy is attached.
    </p>

    <div class="highlight warn">
        <div class="detail"><span class="label">Invoice</span><span class="value">{{ $invoiceNumber }}</span></div>
        <div class="detail"><span class="label">Due date</span><span class="value">{{ $dueDate }}</span></div>
        <div class="detail"><span class="label">Outstanding</span><span class="value">{{ $amountDue }}</span></div>
    </div>

    <p>If payment has already gone out, please reply and tell us when — we will match it up and stop these.</p>
@endsection
