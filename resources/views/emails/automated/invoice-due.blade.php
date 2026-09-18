@extends('emails.automated.layout')

@section('title', 'Invoice due soon')
@section('heading')Invoice {{ $invoiceNumber }}@endsection

@section('body')
    <p>Hello {{ $customerName }},</p>
    <p>A reminder that invoice <strong>{{ $invoiceNumber }}</strong> falls due in {{ $daysUntilDue }} {{ $daysUntilDue === 1 ? 'day' : 'days' }}. A copy is attached.</p>

    <div class="highlight">
        <div class="detail"><span class="label">Invoice</span><span class="value">{{ $invoiceNumber }}</span></div>
        <div class="detail"><span class="label">Due date</span><span class="value">{{ $dueDate }}</span></div>
        <div class="detail"><span class="label">Amount due</span><span class="value">{{ $amountDue }}</span></div>
    </div>

    <p>If it is already on its way, please ignore this. Any questions, just reply.</p>
@endsection
