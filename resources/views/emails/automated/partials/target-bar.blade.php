{{--
    Progress against a whole-number target.

    Small targets get one box per sale, because "three of five" is easier to
    feel as three filled boxes than as sixty percent of a bar. Past twenty the
    boxes stop being countable and it falls back to a bar.

    Expects: $achieved, $target
--}}
@php($pct = $target > 0 ? (int) round(min(1, $achieved / $target) * 100) : 0)
@php($met = $target > 0 && $achieved >= $target)

@if($target > 0 && $target <= 20)
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate; border-spacing:4px 0; margin-left:-4px;">
        <tr>
            @for($i = 1; $i <= $target; $i++)
                <td style="width:26px; height:26px; border-radius:6px;
                           background-color:{{ $i <= $achieved ? ($met ? '#16a34a' : '#2563eb') : '#dbeafe' }};
                           font-size:1px; line-height:1px;">&nbsp;</td>
            @endfor
            @if($achieved > $target)
                <td style="padding-left:6px; font-size:13px; font-weight:700; color:#15803d; white-space:nowrap;">
                    +{{ $achieved - $target }}
                </td>
            @endif
        </tr>
    </table>
@else
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#dbeafe; border-radius:5px;">
        <tr>
            @if($pct > 0)
                <td style="width:{{ $pct }}%; background-color:{{ $met ? '#16a34a' : '#2563eb' }}; height:16px; border-radius:5px; font-size:1px; line-height:1px;">&nbsp;</td>
            @endif
            @if($pct < 100)
                <td style="width:{{ 100 - $pct }}%; height:16px; font-size:1px; line-height:1px;">&nbsp;</td>
            @endif
        </tr>
    </table>
@endif
