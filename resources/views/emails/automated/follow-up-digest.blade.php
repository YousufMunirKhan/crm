@extends('emails.automated.layout')

@section('title', 'Your follow-ups')
@section('heading', 'Follow-ups waiting on you')

@section('body')
    <p>Morning {{ $repName }},</p>

    @if(count($overdue))
        <p><strong>{{ count($overdue) }} overdue</strong> — these were due before today.</p>
        <div class="highlight warn">
            @foreach($overdue as $item)
                <div class="row">
                    <div class="who">{{ $item['customer'] }}</div>
                    <div class="meta"><span class="late">{{ $item['due'] }}</span>@if($item['stage']) · {{ $item['stage'] }}@endif</div>
                    @if($item['phone'])
                        <div class="meta">{{ $item['phone'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if(count($dueToday))
        <p><strong>{{ count($dueToday) }} due today.</strong></p>
        <div class="highlight">
            @foreach($dueToday as $item)
                <div class="row">
                    <div class="who">{{ $item['customer'] }}</div>
                    <div class="meta">{{ $item['due'] }}@if($item['stage']) · {{ $item['stage'] }}@endif</div>
                    @if($item['phone'])
                        <div class="meta">{{ $item['phone'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <p style="font-size: 13px; color: #64748b;">Dates are UK time.</p>
@endsection

@section('footnote', 'Automated message from your CRM.')
