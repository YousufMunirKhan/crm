@extends('emails.automated.layout')

@section('title', 'Appointment reminder')
@section('heading', 'We are seeing you tomorrow')

@section('body')
    <p>Hello {{ $customerName }},</p>
    <p>A quick reminder of your appointment with us tomorrow.</p>

    <div class="highlight">
        <div class="detail"><span class="label">When</span><span class="value">{{ $when }}</span></div>
        @if($place)
            <div class="detail"><span class="label">Where</span><span class="value">{{ $place }}</span></div>
        @endif
        @if($repName)
            <div class="detail"><span class="label">Who is coming</span><span class="value">{{ $repName }}</span></div>
        @endif
        @if($repPhone)
            <div class="detail"><span class="label">Their number</span><span class="value">{{ $repPhone }}</span></div>
        @endif
        @if($about)
            <div class="detail"><span class="label">About</span><span class="value">{{ $about }}</span></div>
        @endif
    </div>

    <p>If tomorrow no longer suits, just reply to this email and we will move it.</p>
@endsection
