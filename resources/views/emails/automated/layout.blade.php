{{--
    The shell every automated email shares.

    Tables and inline styles throughout, because this is read in mail clients
    rather than browsers: Outlook draws with Word, which ignores flexbox, most
    of CSS positioning, and background-image. Anything that has to be seen is
    a table cell with a background colour on it.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title')</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2f7; -webkit-font-smoothing:antialiased;">
    {{-- Shown in the inbox list under the subject, and nowhere else. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">@yield('preview', ' ')</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#eef2f7;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="620"
                       style="width:620px; max-width:100%; background-color:#ffffff; border-radius:14px; overflow:hidden;
                              box-shadow:0 1px 3px rgba(15,23,42,0.08), 0 8px 24px rgba(15,23,42,0.06);">

                    {{-- Logo on white: the mark has its own colours and a coloured bar behind it fights them. --}}
                    <tr>
                        <td align="center" style="padding:26px 28px 18px;">
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" height="40"
                                     style="display:block; height:40px; width:auto; border:0; outline:none; text-decoration:none;">
                            @else
                                <div style="font-family:'Segoe UI',Helvetica,Arial,sans-serif; font-size:19px; font-weight:700; color:#0f172a; letter-spacing:0.01em;">
                                    {{ $companyName }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="height:3px; background-color:#2563eb; font-size:1px; line-height:1px;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 8px; font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
                            <h1 style="margin:0; font-size:21px; line-height:1.3; font-weight:700; color:#0f172a;">
                                @yield('heading')
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:6px 28px 28px; font-family:'Segoe UI',Helvetica,Arial,sans-serif;
                                   font-size:15px; line-height:1.6; color:#334155;">
                            @yield('body')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f8fafc; border-top:1px solid #e2e8f0; padding:20px 28px;
                                   font-family:'Segoe UI',Helvetica,Arial,sans-serif; font-size:12px; line-height:1.7; color:#64748b;">
                            <div style="font-size:13px; font-weight:700; color:#0f172a;">{{ $companyName }}</div>
                            @if(!empty($company['address']))
                                <div>{{ str_replace("\n", ', ', $company['address']) }}</div>
                            @endif
                            <div>
                                @if(!empty($company['phone'])){{ $company['phone'] }}@endif
                                @if(!empty($company['phone']) && !empty($company['email'])) &middot; @endif
                                @if(!empty($company['email']))<a href="mailto:{{ $company['email'] }}" style="color:#2563eb; text-decoration:none;">{{ $company['email'] }}</a>@endif
                            </div>
                            @if(!empty($company['website']))
                                <div><a href="{{ $company['website'] }}" style="color:#2563eb; text-decoration:none;">{{ $company['website'] }}</a></div>
                            @endif
                            <div style="margin-top:10px; color:#94a3b8;">@yield('footnote', 'Automated message from your CRM.')</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
