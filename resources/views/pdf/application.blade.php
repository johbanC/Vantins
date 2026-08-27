<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #1f2937; margin: 0; }
    .wrap { padding: 28px 34px; }
    .head { border-bottom: 3px solid #F59E0B; padding-bottom: 8px; margin-bottom: 14px; }
    .head td { vertical-align: middle; }
    .brand { font-size: 20px; font-weight: bold; color: #0A2452; letter-spacing: 1px; }
    .tag { font-size: 8px; letter-spacing: 2px; color: #6b7280; text-transform: uppercase; }
    .contact { text-align: right; font-size: 9px; color: #6b7280; }
    h2 { background: #0A2452; color: #fff; font-size: 11px; padding: 4px 8px; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: .5px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data td, table.data th { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
    table.data th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
    .kv td { width: 25%; }
    .kv .lbl { background: #f9fafb; font-size: 9px; color: #6b7280; text-transform: uppercase; }
    .totals { margin-top: 8px; text-align: right; font-size: 13px; font-weight: bold; color: #0A2452; }
    .disc { font-size: 9px; color: #374151; line-height: 1.5; margin-top: 6px; }
    .sign td { vertical-align: bottom; padding-top: 18px; }
    .sigimg { height: 60px; border-bottom: 1px solid #111; }
    .foot { margin-top: 22px; border-top: 1px solid #d1d5db; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
    .qrbox { text-align: center; font-size: 8px; color: #6b7280; }
    .qrbox img { width: 90px; height: 90px; }
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

    <table width="100%"><tr>
        <td style="font-size:14px;font-weight:bold;color:#0A2452;">{{ __('app.application_title') }}</td>
        <td style="text-align:right;font-size:9px;color:#6b7280;">
            Ref: {{ $application->verification_code }} &nbsp;&middot;&nbsp; {{ $application->created_at->format('M d, Y') }} &nbsp;&middot;&nbsp; {{ strtoupper($application->status) }}
        </td>
    </tr></table>

    <h2>{{ __('app.applicant_information') }}</h2>
    <table class="data kv">
        <tr><td class="lbl">{{ __('app.company_name') }}</td><td>{{ $application->company_name }}</td><td class="lbl">{{ __('app.company_representative') }}</td><td>{{ $application->company_representative }}</td></tr>
        <tr><td class="lbl">{{ __('app.phone_number') }}</td><td>{{ $application->phone_number }}</td><td class="lbl">{{ __('app.email') }}</td><td>{{ $application->email }}</td></tr>
        <tr><td class="lbl">{{ __('app.mailing_address') }}</td><td>{{ $application->mailing_address }}</td><td class="lbl">{{ __('app.parking_address') }}</td><td>{{ $application->parking_address }}</td></tr>
        <tr><td class="lbl">{{ __('app.effective_date') }}</td><td>{{ optional($application->effective_date)->format('M d, Y') }}</td><td class="lbl">{{ __('app.us_dot_number') }}</td><td>{{ $application->us_dot_number }}</td></tr>
        <tr><td class="lbl">{{ __('app.radius_of_operations') }}</td><td>{{ $application->radius_of_operations }}</td><td class="lbl">{{ __('app.years_in_business') }}</td><td>{{ $application->years_in_business }}</td></tr>
        <tr><td class="lbl">{{ __('app.power_units') }}</td><td>{{ $application->power_units }}</td><td class="lbl">{{ __('app.commodities_hauled') }}</td><td>{{ $application->commodities_hauled }}</td></tr>
    </table>

    @foreach (['drivers' => ['driver_name','dob','cdl_number','state_issued','experience','date_of_hire'], 'vehicles' => ['year','make','vin','body_type','stated_value'], 'trailers' => ['year','make','vin','body_type','stated_value']] as $rel => $cols)
        @if ($application->$rel->count())
            <h2>{{ __('app.'.$rel.'_schedule') }}</h2>
            <table class="data">
                <tr>@foreach ($cols as $c)<th>{{ __('app.'.$c) }}</th>@endforeach</tr>
                @foreach ($application->$rel as $row)
                    <tr>@foreach ($cols as $c)<td>{{ $row->$c }}</td>@endforeach</tr>
                @endforeach
            </table>
        @endif
    @endforeach

    @if ($application->coverages->count())
        <h2>{{ __('app.coverages_list') }}</h2>
        <table class="data">
            <tr><th>{{ __('app.coverage') }}</th><th>{{ __('app.limit') }}</th><th>{{ __('app.deductible') }}</th><th>{{ __('app.premium') }}</th></tr>
            @foreach ($application->coverages as $c)
                <tr><td>{{ $c->coverage }}</td><td>{{ $c->limit_amount }}</td><td>{{ $c->deductible }}</td><td>{{ $c->premium ? '$'.number_format($c->premium, 2) : '' }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="totals">{{ __('app.total_policy_premium') }}: {{ $application->total_policy_premium ? '$'.number_format($application->total_policy_premium, 2) : '—' }}</div>

    <h2>{{ __('app.agency') }}</h2>
    <table class="data kv">
        <tr><td class="lbl">{{ __('app.agency_name') }}</td><td>{{ $application->agency_name }}</td><td class="lbl">{{ __('app.agency_phone') }}</td><td>{{ $application->agency_phone }}</td></tr>
        <tr><td class="lbl">{{ __('app.contact_agent_name') }}</td><td colspan="3">{{ $application->contact_agent_name }}</td></tr>
    </table>

    <h2>{{ __('app.disclosure') }}</h2>
    <p class="disc">{{ __('app.disclosure_body') }}</p>

    <table width="100%" class="sign"><tr>
        <td width="55%">
            @if ($signature)<img src="{{ $signature }}" class="sigimg" alt="signature">@else<div class="sigimg"></div>@endif
            <div style="font-size:9px;color:#6b7280;">{{ __('app.signer_name') }}: {{ $application->signer_name }}</div>
            <div style="font-size:9px;color:#6b7280;">Date: {{ optional($application->disclosure_accepted_at)->format('M d, Y') }}</div>
        </td>
        <td width="45%" class="qrbox">
            <img src="{{ $qr }}" alt="QR"><br>
            To verify the validity of this document, scan this QR code.
        </td>
    </tr></table>

    <div class="foot">+1 (754) 290-0308 &nbsp;&middot;&nbsp; support@vantins.com &nbsp;&middot;&nbsp; 28 W Flagler St Ste 300B #336 Miami, FL 33130 US &nbsp;&middot;&nbsp; https://www.vantins.com/</div>
</div>
</body>
</html>
