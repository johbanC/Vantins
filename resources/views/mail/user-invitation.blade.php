<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('mail.invitation_subject', [], 'es') }} / {{ __('mail.invitation_subject', [], 'en') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif;">
    <span style="display:none; font-size:1px; color:#f3f4f6; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        {{ __('mail.invitation_preheader', [], 'es') }} / {{ __('mail.invitation_preheader', [], 'en') }}
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:10px; overflow:hidden;">

                    <tr>
                        <td style="background-color:#0A2452; padding:28px 36px;">
                            <img src="{{ $message->embed(public_path('images/brand/logo-white.png')) }}" alt="Vantins Insurance Agency" height="34" style="display:block; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F59E0B; height:4px; line-height:4px; font-size:0;">&nbsp;</td>
                    </tr>

                    {{-- The same message in both languages: the whole team is bilingual. --}}
                    @foreach (['es', 'en'] as $lang)
                        <tr>
                            <td style="padding:{{ $loop->first ? '40px 36px 8px' : '8px 36px 8px' }};">
                                <p style="margin:0 0 4px; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#F59E0B; font-weight:bold;">
                                    {{ __('mail.invitation_subject', [], $lang) }}
                                </p>
                                <h1 style="margin:0 0 18px; font-size:20px; line-height:1.3; color:#0A2452; font-weight:bold;">
                                    {{ __('mail.greeting', ['name' => $user->name], $lang) }}
                                </h1>

                                <p style="margin:0 0 14px; font-size:14px; line-height:1.7; color:#374151;">
                                    {{ __('mail.invitation_intro', [], $lang) }}
                                </p>
                                <p style="margin:0 0 22px; font-size:14px; line-height:1.7; color:#374151;">
                                    {{ __('mail.invitation_role', ['role' => __('panel.user.roles.'.$user->role, [], $lang)], $lang) }}
                                </p>

                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 {{ $loop->first ? '30px' : '22px' }};">
                                    <tr>
                                        <td style="border-radius:6px; background-color:#F59E0B;">
                                            <a href="{{ $url }}" style="display:inline-block; padding:13px 26px; font-size:14px; font-weight:bold; color:#0A2452; text-decoration:none;">
                                                {{ __('mail.invitation_button', [], $lang) }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:0 0 {{ $loop->first ? '26px' : '8px' }}; font-size:12px; line-height:1.6; color:#6b7280;">
                                    {{ __('mail.invitation_valid', ['days' => $validDays], $lang) }}
                                </p>

                                @if ($loop->first)
                                    <div style="border-top:1px solid #e5e7eb; margin:0 0 26px;"></div>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="padding:0 36px 32px;">
                            <p style="margin:0 0 28px; font-size:12px; line-height:1.6; color:#6b7280; word-break:break-all;">
                                {{ __('mail.invitation_fallback', [], 'es') }} / {{ __('mail.invitation_fallback', [], 'en') }}<br>
                                <a href="{{ $url }}" style="color:#0A2452;">{{ $url }}</a>
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.6; color:#374151;">
                                <strong style="color:#0A2452;">{{ __('mail.team_name', [], 'es') }} · {{ __('mail.team_name', [], 'en') }}</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 36px; text-align:center;">
                            <p style="margin:0; font-size:11px; color:#9ca3af; line-height:1.6;">
                                {{ __('mail.invitation_ignore', [], 'es') }}<br>
                                {{ __('mail.invitation_ignore', [], 'en') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
