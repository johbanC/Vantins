{{-- $signature (data URI|null), $signerName, $signedAt (formatted|null), $qr, $qrCaption --}}
<table width="100%" class="sign"><tr>
    <td width="58%">
        @if ($signature)<img src="{{ $signature }}" class="sigimg" alt="signature">@else<div class="sigimg"></div>@endif
        <div class="small">{{ __('app.signer_name') }}: {{ $signerName }}</div>
        <div class="small">{{ __('app.date') }}: {{ $signedAt ?: '' }}</div>
    </td>
    <td width="42%" class="qrbox">
        <img src="{{ $qr }}" alt="QR"><br>
        {{ $qrCaption }}
    </td>
</tr></table>
