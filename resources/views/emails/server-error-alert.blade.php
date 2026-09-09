{{-- Plain text on purpose: this gets read on a phone, often in a hurry, and
     sometimes by whoever is on call rather than whoever wrote the code.
     Unescaped output is safe here - the message is never rendered as HTML,
     and escaping turns every quote and ampersand in a stack trace into noise. --}}
Something went wrong on {{ $appUrl }}

{!! $errorMessage !!}

{{ $errorClass }}
{{ $location }}

@foreach ($context as $label => $value)
{{ $label }}: {!! $value !!}
@endforeach

--- first frames ---
{!! $trace !!}

--
You are getting this because your address is in ERROR_ALERT_EMAIL.
Repeats of this same error are held back for {{ config('alerts.throttle_minutes') }} minutes so one
broken page cannot flood you. Everything is in storage/logs/laravel.log either way.
