{{-- $signature (data URI|null), $signerName, $signedAt (formatted|null), $qr, $qrCaption; optional: $representativeName, $representativeTitle, $representativeSignature (countersignature of the agency) --}}
@php($countersigned = ! empty($representativeName))
<table width="100%" class="sign"><tr>
    <td width="{{ $countersigned ? '36%' : '58%' }}">
        @if ($signature)<img src="{{ $signature }}" class="sigimg" alt="signature">@else<div class="sigimg"></div>@endif
        <div class="small">{{ __('app.signer_name') }}: {{ $signerName }}</div>
        <div class="small">{{ __('app.date') }}: {{ $signedAt ?: '' }}</div>
    </td>
    @if ($countersigned)
        <td width="36%">
            @if (! empty($representativeSignature))<img src="{{ $representativeSignature }}" class="sigimg" alt="legal representative signature">@else<div class="sigimg"></div>@endif
            <div class="small">{{ __('app.legal_representative') }}: {{ $representativeName }}</div>
            <div class="small">{{ $representativeTitle ?? '' }}</div>
        </td>
    @endif
    <td width="{{ $countersigned ? '28%' : '42%' }}" class="qrbox">
        <img src="{{ $qr }}" alt="QR"><br>
        {{ $qrCaption }}
    </td>
</tr></table>
