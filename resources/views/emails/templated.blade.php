{{--
    Wrapper for administrator-authored email bodies.

    $bodyHtml has already been rendered and escaped by TemplateRenderer, so it
    is printed unescaped here - escaping it twice would show markup to readers.
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f1;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f1;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background:#ffffff;border:1px solid #dfe5dd;">
                    <tr>
                        <td style="padding:22px 26px;border-bottom:1px solid #e5e8e3;">
                            <span style="color:#245b32;font:700 18px Arial,Helvetica,sans-serif;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;color:#3a443c;font:15px/1.6 Arial,Helvetica,sans-serif;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 26px;border-top:1px solid #e5e8e3;color:#8a938c;font:12px/1.5 Arial,Helvetica,sans-serif;">
                            Diese Nachricht wurde automatisch versendet.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
