<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2f7; font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="min-height:100vh;">
        <tr>
            <td align="center" valign="middle" style="padding:40px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="520"
                       style="width:520px; max-width:100%; background-color:#ffffff; border-radius:14px;
                              box-shadow:0 1px 3px rgba(15,23,42,0.08), 0 8px 24px rgba(15,23,42,0.06);">
                    <tr>
                        <td style="height:4px; background-color:#16a34a; border-radius:14px 14px 0 0; font-size:1px; line-height:1px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:34px 32px;">
                            <h1 style="margin:0 0 12px; font-size:22px; color:#0f172a;">Thank you — that is noted</h1>

                            <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#334155;">
                                We have recorded that invoice <strong>{{ $invoice->invoice_number }}</strong> has already
                                been paid, and we will stop sending you reminders about it.
                            </p>
                            <p style="margin:0 0 14px; font-size:15px; line-height:1.6; color:#334155;">
                                Someone here will match it against our records. If we cannot find it, we will get in
                                touch — we will not chase you automatically again.
                            </p>
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#334155;">
                                Sorry for troubling you, and thank you for letting us know.
                            </p>

                            <p style="margin:26px 0 0; font-size:13px; color:#94a3b8;">{{ $companyName }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
