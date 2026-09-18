{{--
    A column per working day: how many leads went on the board against the
    target, drawn as a filled bar inside a fixed-height track.

    Tables and background-colour only. Gmail strips SVG and ignores
    background-image, so a chart drawn either of those ways is a blank space in
    the client most of these will be read in.

    Expects: $days (date, label, count, target, is_today), $trackHeight
--}}
@php($trackHeight = $trackHeight ?? 96)

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; table-layout:fixed;">
    <tr>
        @foreach($days as $day)
            @php($ratio = $day['target'] > 0 ? min(1, $day['count'] / $day['target']) : 0)
            @php($fill = (int) round($ratio * $trackHeight))
            @php($met = $day['count'] >= $day['target'])
            <td style="vertical-align:bottom; text-align:center; padding:0 3px;">
                <div style="font-size:13px; font-weight:700; color:{{ $met ? '#15803d' : '#0f172a' }}; margin-bottom:4px;">
                    {{ $day['count'] }}
                </div>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                    {{-- The part still to do, so the target is visible as the top of the track. --}}
                    <tr>
                        <td style="height:{{ max(0, $trackHeight - $fill) }}px; background-color:#e2e8f0; font-size:1px; line-height:1px;">&nbsp;</td>
                    </tr>
                    @if($fill > 0)
                        <tr>
                            <td style="height:{{ $fill }}px; background-color:{{ $met ? '#16a34a' : '#2563eb' }}; font-size:1px; line-height:1px;">&nbsp;</td>
                        </tr>
                    @endif
                </table>

                <div style="font-size:12px; color:{{ $day['is_today'] ? '#0f172a' : '#64748b' }}; font-weight:{{ $day['is_today'] ? '700' : '400' }}; margin-top:6px;">
                    {{ $day['label'] }}
                </div>
            </td>
        @endforeach
    </tr>
</table>

<p style="margin:10px 0 0; font-size:12px; color:#64748b;">
    Full column is the daily target of {{ $days[0]['target'] ?? 5 }}. Sundays are not counted.
</p>
