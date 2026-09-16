@extends('emails.layout', [
    'title' => 'Your password was changed',
    'preheader' => 'The password for your Attendance account was updated just now.',
    'eyebrow' => 'Security',
    'heading' => 'Your password was changed',
])

@section('content')
    <p class="text-main" style="margin:0 0 16px 0;font-size:16px;line-height:1.65;color:#334155;">
        Hi {{ $name }}, the password for <strong style="color:#0F172A;">{{ $email }}</strong>
        was changed successfully.
    </p>
    <p class="text-muted" style="margin:0 0 28px 0;font-size:15px;line-height:1.65;color:#64748B;">
        Other signed-in sessions have been signed out. If this was you, no further action is needed.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px 0;">
        <tr>
            <td>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" bgcolor="{{ config('api.mail_brand_color', '#0D9488') }}" style="border-radius:10px;background-color:{{ config('api.mail_brand_color', '#0D9488') }};">
                            <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:650;color:#ffffff;text-decoration:none;">
                                Sign in
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FEF2F2;border-radius:12px;">
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:1.55;color:#9F1239;">
                If you didn’t change your password, reset it immediately and contact an administrator.
            </td>
        </tr>
    </table>
@endsection
