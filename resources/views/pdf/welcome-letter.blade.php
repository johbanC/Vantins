<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #1f2937; margin: 0; }
    .wrap { padding: 34px 44px; }
    .head { border-bottom: 3px solid #F59E0B; padding-bottom: 8px; margin-bottom: 26px; }
    .head td { vertical-align: middle; }
    .contact { text-align: right; font-size: 9px; color: #6b7280; }
    .date { font-size: 11px; color: #374151; margin-bottom: 34px; }
    .title { text-align: center; font-size: 20px; font-weight: bold; color: #0A2452; letter-spacing: 2px; text-transform: uppercase; }
    .recipient { text-align: center; font-size: 14px; font-weight: bold; color: #F59E0B; margin-top: 6px; margin-bottom: 30px; }
    .body p { text-align: center; line-height: 1.7; margin: 0 0 16px; padding: 0 6%; }
    .sign-off { text-align: center; margin-top: 34px; font-size: 12px; color: #0A2452; font-weight: bold; }
    .foot { margin-top: 46px; border-top: 1px solid #d1d5db; padding-top: 8px; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="wrap">
    <table class="head" width="100%"><tr>
        <td><img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/brand/logo-dark.png'))) }}" style="height:40px"></td>
        <td class="contact">
            +1 (754) 290-0308 &nbsp;|&nbsp; support@vantins.com<br>
            28 W Flagler St Ste 300B #336 Miami, FL 33130 US<br>
            https://www.vantins.com/
        </td>
    </tr></table>

    <div class="date">{{ $sentAt?->format('m/d/Y') }}</div>

    <div class="title">{{ __('app.welcome_letter_title') }}</div>
    <div class="recipient">{{ $recipient }}</div>

    <div class="body">
        <p>{{ __('app.welcome_letter_p1') }}</p>
        <p>{{ __('app.welcome_letter_p2') }}</p>
        <p>{{ __('app.welcome_letter_p3') }}</p>
    </div>

    <div class="sign-off">{{ __('app.welcome_letter_signoff') }}</div>

    <div class="foot">+1 (754) 290-0308 &nbsp;&middot;&nbsp; support@vantins.com &nbsp;&middot;&nbsp; 28 W Flagler St Ste 300B #336 Miami, FL 33130 US &nbsp;&middot;&nbsp; https://www.vantins.com/</div>
</div>
</body>
</html>
