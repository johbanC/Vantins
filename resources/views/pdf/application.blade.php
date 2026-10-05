<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf.partials.styles')
</head>
<body>
@include('pdf.partials.header', [
    'title' => __('app.application_title'),
    'reference' => $application->verification_code,
    'date' => \App\Support\Format::date($application->created_at),
    'status' => __('app.status_names.'.$application->status),
    'isDemo' => $application->is_demo,
])

<div class="notice">{{ __('app.pdf_application_notice') }}</div>

<div class="section">
    <h2>{{ __('app.applicant_information') }}</h2>
    <table class="data kv">
        <tr><td class="lbl">{{ __('app.company_name') }}</td><td>{{ $application->company_name }}</td><td class="lbl">{{ __('app.company_representative') }}</td><td>{{ $application->company_representative }}</td></tr>
        <tr><td class="lbl">{{ __('app.phone_number') }}</td><td>{{ $application->phone_number }}</td><td class="lbl">{{ __('app.email') }}</td><td>{{ $application->email }}</td></tr>
        <tr><td class="lbl">{{ __('app.mailing_address') }}</td><td>{{ $application->mailing_address }}</td><td class="lbl">{{ __('app.parking_address') }}</td><td>{{ $application->parking_address }}</td></tr>
        <tr><td class="lbl">{{ __('app.effective_date') }}</td><td>{{ \App\Support\Format::date($application->effective_date) }}</td><td class="lbl">{{ __('app.us_dot_number') }}</td><td>{{ $application->us_dot_number }}</td></tr>
        <tr><td class="lbl">{{ __('app.radius_of_operations') }}</td><td>{{ $application->radius_of_operations }}</td><td class="lbl">{{ __('app.years_in_business') }}</td><td>{{ $application->years_in_business }}</td></tr>
        <tr><td class="lbl">{{ __('app.power_units') }}</td><td>{{ $application->power_units }}</td><td class="lbl">{{ __('app.commodities_hauled') }}</td><td>{{ $application->commodities_hauled }}</td></tr>
    </table>
</div>

@foreach (['drivers' => ['driver_name','dob','cdl_number','state_issued','cdl_issue_date','cdl_expiry_date','experience','date_of_hire'], 'vehicles' => ['year','make','vin','body_type','garaging_zip','stated_value','physical_damage'], 'trailers' => ['year','make','vin','body_type','stated_value']] as $rel => $cols)
    @if ($application->$rel->count())
        <div class="section">
            <h2>{{ __('app.'.$rel.'_schedule') }}</h2>
            <table class="data" style="font-size:{{ count($cols) > 7 ? '8.5' : '10' }}px">
                <tr>@foreach ($cols as $c)<th>{{ __('app.'.($c === 'physical_damage' ? 'has_physical_damage' : $c)) }}</th>@endforeach</tr>
                @foreach ($application->$rel as $row)
                    <tr>@foreach ($cols as $c)
                        <td>
                            @if ($c === 'stated_value'){{ \App\Support\Format::money($row->$c) }}
                            @elseif ($c === 'physical_damage')
                                @if ($row->has_physical_damage){{ \App\Support\Format::money($row->physical_damage_value) }} / {{ __('app.deductible') }} {{ \App\Support\Format::money($row->physical_damage_deductible) }}@else{{ __('app.no') }}@endif
                            @elseif (in_array($c, ['dob','date_of_hire','cdl_issue_date','cdl_expiry_date'])){{ \App\Support\Format::date($row->$c) }}
                            @else{{ $row->$c }}@endif
                        </td>
                    @endforeach</tr>
                @endforeach
            </table>
        </div>
    @endif
@endforeach

@if ($application->coverages->count())
    <div class="section">
        <h2>{{ __('app.coverages_list') }}</h2>
        <table class="data">
            <tr><th>{{ __('app.coverage') }}</th><th>{{ __('app.limit') }}</th><th>{{ __('app.deductible') }}</th><th>{{ __('app.coverage_details') }}</th></tr>
            @foreach ($application->coverages as $c)
                <tr>
                    <td>{{ $c->displayName() }}</td>
                    <td>{{ $c->displayLimit() }}</td>
                    <td>{{ \App\Models\Coverage::formatAmount($c->deductible) }}</td>
                    <td style="font-size:8.5px">@foreach ($c->detailLines() as $line){{ $line }}<br>@endforeach</td>
                </tr>
            @endforeach
        </table>
        <div class="small" style="margin-top:3px">{{ __('app.coverage_intent_note') }}</div>
    </div>
@endif

<div class="section">
    <h2>{{ __('app.finance_proposal') }} <span class="tag" style="color:#fcd34d">&mdash; {{ __('app.pdf_estimated') }}</span></h2>
    <table class="data kv">
        <tr>
            <td class="lbl">{{ __('app.down_payment') }}</td><td>{{ \App\Support\Format::money($application->down_payment) }}</td>
            <td class="lbl">{{ __('app.number_of_payments') }}</td><td>{{ $application->number_of_payments ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">{{ __('app.monthly_payment') }}</td><td>{{ \App\Support\Format::money($application->monthly_payment) }}</td>
            <td class="lbl">{{ __('app.total_policy_premium') }}</td><td><strong>{{ \App\Support\Format::money($application->total_policy_premium) }}</strong></td>
        </tr>
    </table>
    <div class="small" style="margin-top:3px">{{ __('app.pdf_finance_note') }}</div>
</div>

{{-- Agency, declaration and signature stay together on the same page. --}}
<div class="keep">
    @include('pdf.partials.agency', [
        'agencyName' => $application->agency_name,
        'agencyPhone' => $application->agency_phone,
        'contactAgent' => $application->contact_agent_name,
    ])

    <h2>{{ __('app.disclosure') }}</h2>
    <p class="disc">{{ __('app.disclosure_body') }}</p>

    @include('pdf.partials.signature', [
        'signature' => $signature,
        'signerName' => $application->signer_name,
        'signedAt' => $application->disclosure_accepted_at ? \App\Support\Format::date($application->disclosure_accepted_at) : null,
        'qr' => $qr,
        'qrCaption' => __('app.qr_caption'),
    ])
</div>

@include('pdf.partials.footer')
</body>
</html>
