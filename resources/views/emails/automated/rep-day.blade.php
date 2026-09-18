@extends('emails.automated.layout')

@section('title', 'Your appointments today')
@section('heading', 'Your day')

@section('body')
    <p>Morning {{ $repName }},</p>
    <p>
        {{ count($appointments) }}
        {{ count($appointments) === 1 ? 'appointment' : 'appointments' }} today, {{ $today }}.
    </p>

    <div class="highlight">
        @foreach($appointments as $apt)
            <div class="row">
                <div class="who">{{ $apt['time'] }} — {{ $apt['customer'] }}</div>
                @if($apt['place'])
                    <div class="meta">{{ $apt['place'] }}</div>
                @endif
                @if($apt['phone'])
                    <div class="meta">{{ $apt['phone'] }}</div>
                @endif
                @if($apt['about'])
                    <div class="meta">{{ $apt['about'] }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <p style="font-size: 13px; color: #64748b;">Times are UK time.</p>
@endsection

@section('footnote', 'Automated message from your CRM.')
