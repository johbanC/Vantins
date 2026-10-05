{{-- $title, $reference, $date, $status (optional) --}}
@if (! empty($isDemo))
    <div class="demo">{{ __('app.demo_badge') }}</div>
@endif

<table class="head" width="100%"><tr>
    <td><img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/brand/logo-dark.png'))) }}" style="height:38px"></td>
    <td class="contact">
        {{ config('vantins.agency_phone') }} &nbsp;|&nbsp; support@vantins.com<br>
        28 W Flagler St Ste 300B #336 Miami, FL 33130 US<br>
        https://www.vantins.com/
    </td>
</tr></table>

<table width="100%"><tr>
    <td class="title">{{ $title }}</td>
    <td class="meta">
        {{ __('app.ref') }}: {{ $reference }} &nbsp;&middot;&nbsp; {{ $date }}@if (! empty($status)) &nbsp;&middot;&nbsp; {{ mb_strtoupper($status) }}@endif
    </td>
</tr></table>
