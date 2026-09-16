<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title ?? config('app.name') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        :root { color-scheme: light dark; }
        html, body { margin: 0 !important; padding: 0 !important; height: 100% !important; width: 100% !important; }
        * { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        a { text-decoration: none; }
        @media (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 24px !important; padding-right: 24px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .bg-page { background-color: #0B1220 !important; }
            .bg-card { background-color: #111827 !important; }
            .text-main { color: #F8FAFC !important; }
            .text-muted { color: #94A3B8 !important; }
            .rule { border-color: #1F2937 !important; }
            .chip { background-color: #0F172A !important; color: #99F6E4 !important; }
        }
    </style>
</head>
@php
    $brand = $brand ?? config('api.mail_brand_color', '#0D9488');
    $appName = config('app.name', 'Attendance');
    $preheader = $preheader ?? '';
@endphp
<body class="bg-page" style="margin:0;padding:0;background-color:#EEF2F6;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
    {{ $preheader }}
    &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-page" style="background-color:#EEF2F6;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" class="container" style="width:560px;max-width:560px;">
                <tr>
                    <td style="padding:0 8px 20px 8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td valign="middle">
                                    @if (!empty($logoUrl ?? config('api.mail_logo_url')))
                                        <img src="{{ $logoUrl ?? config('api.mail_logo_url') }}" alt="{{ $appName }}" width="36" height="36" style="display:block;border-radius:10px;">
                                    @else
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="36" height="36" align="center" valign="middle" style="background-color:{{ $brand }};border-radius:10px;color:#ffffff;font-size:16px;font-weight:700;letter-spacing:-0.04em;">
                                                    A
                                                </td>
                                            </tr>
                                        </table>
                                    @endif
                                </td>
                                <td valign="middle" style="padding-left:12px;">
                                    <div style="font-size:11px;letter-spacing:0.16em;text-transform:uppercase;color:#64748B;font-weight:600;">Attendance</div>
                                    <div class="text-main" style="font-size:15px;color:#0F172A;font-weight:650;letter-spacing:-0.02em;">PUSH Communication</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="bg-card" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,0.06),0 12px 32px rgba(15,23,42,0.06);">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td height="4" style="background-color:{{ $brand }};font-size:0;line-height:0;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td class="px" style="padding:36px 40px 40px 40px;">
                                    @if (!empty($eyebrow))
                                        <div class="chip" style="display:inline-block;background-color:#F0FDFA;color:{{ $brand }};font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:6px 10px;border-radius:999px;margin-bottom:18px;">
                                            {{ $eyebrow }}
                                        </div>
                                    @endif
                                    <h1 class="text-main" style="margin:0 0 16px 0;font-size:26px;line-height:1.25;letter-spacing:-0.03em;color:#0F172A;font-weight:700;">
                                        {{ $heading }}
                                    </h1>
                                    @yield('content')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 8px 0 8px;">
                        <p class="text-muted" style="margin:0 0 8px 0;font-size:12px;line-height:1.6;color:#64748B;">
                            This message was sent by {{ $appName }}. If you weren’t expecting it, you can ignore it.
                        </p>
                        <p class="text-muted" style="margin:0;font-size:12px;line-height:1.6;color:#94A3B8;">
                            © {{ date('Y') }} {{ $appName }} · Africa/Kigali
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
