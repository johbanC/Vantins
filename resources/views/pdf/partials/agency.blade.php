{{-- $agencyName, $agencyPhone, $contactAgent --}}
<h2>{{ __('app.agency') }}</h2>
<table class="data kv">
    <tr>
        <td class="lbl">{{ __('app.agency_name') }}</td><td>{{ $agencyName }}</td>
        <td class="lbl">{{ __('app.agency_phone') }}</td><td>{{ $agencyPhone }}</td>
    </tr>
    <tr><td class="lbl">{{ __('app.contact_agent_name') }}</td><td colspan="3">{{ $contactAgent }}</td></tr>
</table>
