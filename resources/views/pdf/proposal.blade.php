<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf.partials.styles')
</head>
<body>
@php
    $money = fn ($v) => \App\Support\Format::money($v);
    $name = fn (array $line) => $locale === 'es' ? $line['name_es'] : $line['name_en'];
@endphp

@include('pdf.partials.header', [
    'title' => __('app.proposal_title'),
    'reference' => $reference,
    'date' => $issued,
    'status' => $accepted ? __('app.pdf_status_accepted') : __('app.pdf_status_pending'),
    'isDemo' => $isDemo,
])

<div class="notice">{{ __('app.pdf_proposal_notice') }}</div>

<div class="section">
    <h2>{{ __('app.proposal_title') }}</h2>
    <table class="data kv">
        <tr><td class="lbl">{{ __('app.company_name') }}</td><td>{{ $d['company'] }}</td><td class="lbl">{{ __('app.us_dot_number') }}</td><td>{{ $d['us_dot_number'] ?: '—' }}</td></tr>
        <tr><td class="lbl">{{ __('app.effective_date') }}</td><td>{{ \App\Support\Format::date($d['effective_date']) }}</td><td class="lbl">{{ __('app.proposal_valid_until') }}</td><td>{{ \App\Support\Format::date($d['valid_until']) }}</td></tr>
        @if ($d['product'])
            <tr><td class="lbl">{{ __('app.product') }}</td><td colspan="3">{{ $d['product'] }}</td></tr>
        @endif
    </table>
</div>

<div class="section">
    <h2>{{ __('app.proposal_coverages') }}</h2>
    <table class="data">
        <tr><th style="width:42%">{{ __('app.coverage') }}</th><th>{{ __('app.limit') }}</th><th>{{ __('app.deductible') }}</th></tr>
        @foreach ($d['coverages'] as $line)
            <tr>
                <td>{{ $name($line) }}</td>
                <td>{{ $money($line['limit']) }}@if ($line['aggregate']) / {{ __('app.aggregate') }} {{ $money($line['aggregate']) }}@endif</td>
                <td>{{ $money($line['deductible']) }}</td>
            </tr>
        @endforeach
    </table>
</div>

<div class="section">
    <h2>{{ __('app.proposal_pricing') }} <span class="tag" style="color:#fcd34d">&mdash; {{ __('app.pdf_estimated') }}</span></h2>
    <table class="data">
        <tr><td>{{ __('app.proposal_premium') }}</td><td class="num">{{ $money($d['carrier_premium']) }}</td></tr>
        <tr><td>{{ __('app.proposal_fees') }}</td><td class="num">{{ $money($d['fees']) }}</td></tr>
        <tr><td>{{ __('app.proposal_agency_fee') }}</td><td class="num">{{ $money($d['producer_fee']) }}</td></tr>
        <tr class="strong"><td>{{ __('app.proposal_total_cost') }}</td><td class="num">{{ $money($d['total_cost']) }}</td></tr>
        <tr><td>{{ __('app.down_payment') }}</td><td class="num">{{ $money($d['down_payment']) }}</td></tr>
        @if ($d['number_of_payments'])
            <tr><td>{{ __('app.proposal_payments') }}</td><td class="num">{{ $d['number_of_payments'] }} &times; {{ $money($d['installment_amount']) }} = {{ $money($d['amount_financed']) }}</td></tr>
        @endif
        <tr class="total"><td>{{ __('app.proposal_total_payable') }}</td><td class="num">{{ $money($d['total_payable']) }}</td></tr>
    </table>
    <div class="small" style="margin-top:3px">{{ __('app.proposal_estimate_note') }}</div>
</div>

<div class="keep">
    @include('pdf.partials.agency', ['agencyName' => $agencyName, 'agencyPhone' => $agencyPhone, 'contactAgent' => $contactAgent])

    <h2>{{ __('app.proposal_disclosure_title') }}</h2>
    <p class="disc">{{ __('app.proposal_disclosure_body') }}</p>

    @include('pdf.partials.signature', [
        'signature' => $signature,
        'signerName' => $signerName,
        'signedAt' => $signedAt,
        'qr' => $qr,
        'qrCaption' => __('app.qr_caption'),
    ])
</div>

@include('pdf.partials.footer')
</body>
</html>
