@php
    $sectionTitle = 'mb-3 mt-6 text-sm font-semibold uppercase tracking-wide text-brand';
    $kvWrap = 'grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2';
    $kvLabel = 'text-xs uppercase tracking-wide text-white/40';
    $kvValue = 'text-sm text-white';
    $th = 'px-2 py-1 text-left text-xs font-medium uppercase tracking-wide text-white/40';
    $td = 'px-2 py-1 text-sm text-white align-top';
    $money = fn ($v) => $v ? '$'.number_format((float) $v, 2) : '—';
@endphp

<div class="space-y-1">
    {{-- Applicant Information --}}
    <h3 class="{{ $sectionTitle }} mt-0">{{ __('app.applicant_information') }}</h3>
    <div class="{{ $kvWrap }}">
        @foreach ([
            'company_name', 'company_representative', 'phone_number', 'email',
            'mailing_address', 'parking_address', 'us_dot_number',
            'radius_of_operations', 'years_in_business', 'power_units',
        ] as $f)
            <div>
                <div class="{{ $kvLabel }}">{{ __('app.'.$f) }}</div>
                <div class="{{ $kvValue }}">{{ $application->$f ?: '—' }}</div>
            </div>
        @endforeach
        <div>
            <div class="{{ $kvLabel }}">{{ __('app.effective_date') }}</div>
            <div class="{{ $kvValue }}">{{ optional($application->effective_date)->format('m/d/Y') ?: '—' }}</div>
        </div>
        <div class="sm:col-span-2">
            <div class="{{ $kvLabel }}">{{ __('app.commodities_hauled') }}</div>
            <div class="{{ $kvValue }} whitespace-pre-line">{{ $application->commodities_hauled ?: '—' }}</div>
        </div>
    </div>

    {{-- Schedules --}}
    @foreach ([
        'drivers' => ['driver_name','dob','cdl_number','state_issued','experience','date_of_hire'],
        'vehicles' => ['year','make','vin','body_type','stated_value'],
        'trailers' => ['year','make','vin','body_type','stated_value'],
    ] as $rel => $cols)
        <h3 class="{{ $sectionTitle }}">{{ __('app.'.$rel.'_schedule') }}</h3>
        @if ($application->$rel->isEmpty())
            <p class="text-sm text-white/40">{{ __('app.none_yet') }}</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-white/10">
                <table class="min-w-full divide-y divide-white/10">
                    <thead><tr>@foreach ($cols as $c)<th class="{{ $th }}">{{ __('app.'.$c) }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($application->$rel as $row)
                            <tr>
                                @foreach ($cols as $c)
                                    <td class="{{ $td }}">
                                        @if ($c === 'stated_value'){{ $money($row->$c) }}
                                        @elseif (in_array($c, ['dob','date_of_hire'])){{ optional($row->$c)->format('m/d/Y') ?: '—' }}
                                        @else{{ $row->$c ?: '—' }}@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    {{-- Coverages --}}
    <h3 class="{{ $sectionTitle }}">{{ __('app.coverages_list') }}</h3>
    @if ($application->coverages->isEmpty())
        <p class="text-sm text-white/40">{{ __('app.none_yet') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-white/10">
            <table class="min-w-full divide-y divide-white/10">
                <thead><tr>
                    <th class="{{ $th }}">{{ __('app.coverage') }}</th>
                    <th class="{{ $th }}">{{ __('app.limit') }}</th>
                    <th class="{{ $th }}">{{ __('app.deductible') }}</th>
                    <th class="{{ $th }}">{{ __('app.premium') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($application->coverages as $c)
                        <tr>
                            <td class="{{ $td }}">{{ $c->coverage ?: '—' }}</td>
                            <td class="{{ $td }}">{{ $c->limit_amount ?: '—' }}</td>
                            <td class="{{ $td }}">{{ $c->deductible ?: '—' }}</td>
                            <td class="{{ $td }}">{{ $money($c->premium) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Finance Proposal --}}
    <h3 class="{{ $sectionTitle }}">{{ __('app.finance_proposal') }}</h3>
    <div class="{{ $kvWrap }}">
        <div><div class="{{ $kvLabel }}">{{ __('app.down_payment') }}</div><div class="{{ $kvValue }}">{{ $money($application->down_payment) }}</div></div>
        <div><div class="{{ $kvLabel }}">{{ __('app.number_of_payments') }}</div><div class="{{ $kvValue }}">{{ $application->number_of_payments ?: '—' }}</div></div>
        <div><div class="{{ $kvLabel }}">{{ __('app.monthly_payment') }}</div><div class="{{ $kvValue }}">{{ $money($application->monthly_payment) }}</div></div>
        <div><div class="{{ $kvLabel }}">{{ __('app.total_policy_premium') }}</div><div class="text-base font-semibold text-brand">{{ $money($application->total_policy_premium) }}</div></div>
    </div>

    {{-- Agency --}}
    <h3 class="{{ $sectionTitle }}">{{ __('app.agency') }}</h3>
    <div class="{{ $kvWrap }}">
        <div><div class="{{ $kvLabel }}">{{ __('app.agency_name') }}</div><div class="{{ $kvValue }}">{{ $application->agency_name ?: '—' }}</div></div>
        <div><div class="{{ $kvLabel }}">{{ __('app.agency_phone') }}</div><div class="{{ $kvValue }}">{{ $application->agency_phone ?: '—' }}</div></div>
        <div><div class="{{ $kvLabel }}">{{ __('app.contact_agent_name') }}</div><div class="{{ $kvValue }}">{{ $application->contact_agent_name ?: '—' }}</div></div>
    </div>
</div>
