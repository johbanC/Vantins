<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('mail.welcome_letter_subject') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif;">
    <span style="display:none; font-size:1px; color:#f3f4f6; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        {{ __('mail.welcome_letter_preheader') }}
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:10px; overflow:hidden;">

                    <tr>
                        <td style="background-color:#0A2452; padding:28px 36px;">
                            <img src="{{ $logo }}" alt="Vantins Insurance Agency" height="34" style="display:block; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F59E0B; height:4px; line-height:4px; font-size:0;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:40px 36px 8px;">
                            <p style="margin:0 0 4px; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#F59E0B; font-weight:bold;">
                                {{ __('mail.welcome_letter_subject') }}
                            </p>
                            <h1 style="margin:0 0 22px; font-size:22px; line-height:1.3; color:#0A2452; font-weight:bold;">
                                {{ __('mail.greeting', ['name' => $recipient]) }}
                            </h1>

                            <p style="margin:0 0 18px; font-size:14px; line-height:1.7; color:#374151;">
                                {{ __('mail.welcome_letter_intro') }}
                            </p>
                            <p style="margin:0 0 18px; font-size:14px; line-height:1.7; color:#374151;">
                                {{ __('mail.welcome_letter_body') }}
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px; width:100%; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:13px; color:#6b7280;">
                                        📎 <strong style="color:#0A2452;">Vantins-welcome-letter.pdf</strong>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 28px; font-size:14px; line-height:1.7; color:#374151;">
                                {{ __('mail.welcome_letter_body2') }}
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.6; color:#374151;">
                                {{ __('mail.signoff') }}<br>
                                <strong style="color:#0A2452;">{{ __('mail.team_name') }}</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 36px 36px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:6px; background-color:#0A2452;">
                                        <a href="tel:+17542900308" style="display:inline-block; padding:11px 18px; font-size:13px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                            {{ __('mail.call_us') }} · +1 (754) 290-0308
                                        </a>
                                    </td>
                                    <td style="width:10px;"></td>
                                    <td style="border-radius:6px; border:1px solid #d1d5db;">
                                        <a href="mailto:support@vantins.com" style="display:inline-block; padding:11px 18px; font-size:13px; font-weight:bold; color:#0A2452; text-decoration:none;">
                                            {{ __('mail.email_us') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 36px; text-align:center;">
                            <p style="margin:0; font-size:11px; color:#9ca3af; line-height:1.6;">
                                +1 (754) 290-0308 &nbsp;&middot;&nbsp; support@vantins.com<br>
                                28 W Flagler St Ste 300B #336 Miami, FL 33130 US<br>
                                https://www.vantins.com/
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
