@extends('emails.layout', [
    'title' => 'Reset your password',
    'preheader' => 'Use this secure link to choose a new password. It expires in '.$minutes.' minutes.',
    'eyebrow' => 'Security',
    'heading' => 'Reset your password',
])

@section('content')
    <p class="text-main" style="margin:0 0 16px 0;font-size:16px;line-height:1.65;color:#334155;">
        Hi {{ $name }}, we received a request to reset the password for
        <strong style="color:#0F172A;">{{ $email }}</strong>.
    </p>
    <p class="text-muted" style="margin:0 0 28px 0;font-size:15px;line-height:1.65;color:#64748B;">
        Click the button below to choose a new password. If you didn’t ask for this, you can ignore this email — your current password will stay the same.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px 0;">
        <tr>
            <td>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" bgcolor="{{ config('api.mail_brand_color', '#0D9488') }}" style="border-radius:10px;background-color:{{ config('api.mail_brand_color', '#0D9488') }};">
                            <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:650;color:#ffffff;text-decoration:none;letter-spacing:-0.01em;">
                                Reset password
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="chip" style="background-color:#F8FAFC;border-radius:12px;margin-bottom:24px;">
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:1.55;color:#475569;">
                This link expires in <strong style="color:#0F172A;">{{ $minutes }} minutes</strong>.
                After that you’ll need to request a new one.
            </td>
        </tr>
    </table>

    <p class="text-muted" style="margin:0 0 8px 0;font-size:12px;line-height:1.6;color:#94A3B8;">
        Button not working? Paste this URL into your browser:
    </p>
    <p style="margin:0;font-size:12px;line-height:1.6;word-break:break-all;">
        <a href="{{ $url }}" style="color:{{ config('api.mail_brand_color', '#0D9488') }};">{{ $url }}</a>
    </p>
@endsection
