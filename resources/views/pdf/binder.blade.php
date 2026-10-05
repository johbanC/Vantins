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
    'title' => __('app.binder_title'),
    'reference' => $reference,
    'date' => $issued,
    'status' => $statusLabel,
    'isDemo' => $isDemo,
])

<div class="notice">{{ __('app.pdf_binder_notice') }}</div>

<div class="section">
    <h2>{{ __('app.binder_title') }}</h2>
    <table class="data kv">
        <tr><td class="lbl">{{ __('app.company_name') }}</td><td>{{ $d['company'] }}</td><td class="lbl">{{ __('app.us_dot_number') }}</td><td>{{ $d['us_dot_number'] ?: '—' }}</td></tr>
        <tr><td class="lbl">{{ __('app.binder_number') }}</td><td>{{ $quote->binder_number }}</td><td class="lbl">{{ __('app.binder_effective_date') }}</td><td>{{ \App\Support\Format::date($quote->binder_effective_date) }}</td></tr>
        @if ($carrierName)
            <tr><td class="lbl">{{ __('app.insurance_company') }}</td><td colspan="3">{{ $carrierName }}</td></tr>
        @endif
        @if ($quote->policy_number)
            <tr><td class="lbl">{{ __('app.policy_number') }}</td><td colspan="3">{{ $quote->policy_number }}</td></tr>
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
    <h2>{{ __('app.proposal_pricing') }} <span class="tag ok" style="color:#6ee7b7">&mdash; {{ __('app.pdf_confirmed') }}</span></h2>
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
        <tr>
            <td>{{ __('app.payment_status') }}</td>
            <td class="num">
                @if ($quote->paid_at)
                    {{ __('app.payment_received') }} &middot; {{ \App\Support\Format::date($quote->paid_at) }}
                @else
                    {{ __('app.payment_pending') }}
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="keep">
    @include('pdf.partials.agency', ['agencyName' => $agencyName, 'agencyPhone' => $agencyPhone, 'contactAgent' => $contactAgent])

    <table width="100%" class="sign"><tr>
        <td width="58%"><div class="small">{{ __('app.binder_note') }}</div></td>
        <td width="42%" class="qrbox"><img src="{{ $qr }}" alt="QR"><br>{{ __('app.qr_caption') }}</td>
    </tr></table>
</div>

@include('pdf.partials.footer')
</body>
</html>
