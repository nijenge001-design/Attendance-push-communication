@extends('emails.layout', [
    'title' => 'Your account is ready',
    'preheader' => 'An Attendance PUSH Communication account was created for you.',
    'eyebrow' => 'Welcome',
    'heading' => 'Your account is ready',
])

@section('content')
    <p class="text-main" style="margin:0 0 16px 0;font-size:16px;line-height:1.65;color:#334155;">
        Hi {{ $name }}, an administrator created an account for you on
        <strong style="color:#0F172A;">{{ config('app.name') }}</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F8FAFC;border-radius:12px;margin:0 0 28px 0;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 10px 0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#94A3B8;font-weight:700;">Sign-in details</p>
                <p class="text-main" style="margin:0 0 6px 0;font-size:15px;color:#0F172A;">
                    Username · <strong>{{ $username }}</strong>
                </p>
                <p class="text-main" style="margin:0;font-size:15px;color:#0F172A;">
                    Role · <strong>{{ $role }}</strong>
                </p>
            </td>
        </tr>
    </table>

    <p class="text-muted" style="margin:0 0 28px 0;font-size:15px;line-height:1.65;color:#64748B;">
        Use the password your administrator shared with you, then change it after your first sign-in.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" bgcolor="{{ config('api.mail_brand_color', '#0D9488') }}" style="border-radius:10px;background-color:{{ config('api.mail_brand_color', '#0D9488') }};">
                <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:650;color:#ffffff;text-decoration:none;">
                    Open the app
                </a>
            </td>
        </tr>
    </table>
@endsection
