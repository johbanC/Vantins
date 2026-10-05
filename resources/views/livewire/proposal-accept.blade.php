@php
    $btn = 'rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-navy-dark hover:bg-brand-600';
    $card = 'rounded-2xl border border-white/10 bg-black/30 p-5 sm:p-8';
    $sectionTitle = 'mb-3 mt-6 text-sm font-semibold uppercase tracking-wide text-brand';
    $th = 'px-2 py-1 text-left text-xs font-medium uppercase tracking-wide text-white/40';
    $td = 'px-2 py-1 text-sm text-white align-top';
    $money = fn ($v) => \App\Support\Format::money($v);
    $application = $quote->application;
@endphp

<div class="space-y-6" x-data>
    <div class="flex justify-end gap-2 text-xs">
        <button wire:click="switchLocale('en')" class="rounded px-2 py-1 {{ $locale === 'en' ? 'bg-brand text-navy-dark' : 'bg-white/10' }}">EN</button>
        <button wire:click="switchLocale('es')" class="rounded px-2 py-1 {{ $locale === 'es' ? 'bg-brand text-navy-dark' : 'bg-white/10' }}">ES</button>
    </div>

    @if ($application->is_demo)
        <p class="rounded-lg border border-brand/40 bg-brand/10 px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-brand">{{ __('app.demo_notice') }}</p>
    @endif

    @if ($done === 'accepted')
        <div class="{{ $card }} py-10 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-brand text-navy-dark">&check;</div>
            <h2 class="text-lg font-semibold">{{ __('app.proposal_thanks_title') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-white/60">{{ __('app.proposal_thanks_body') }}</p>
        </div>

    @elseif (in_array($state, ['expired', 'revoked', 'unavailable']))
        <div class="{{ $card }} py-10 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-500/20 text-2xl text-red-300">&times;</div>
            <h2 class="text-lg font-semibold">{{ __('app.proposal_'.$state.'_title') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-white/60">{{ __('app.proposal_'.$state.'_body') }}</p>
        </div>

    @else
        <div class="{{ $card }}">
            @if ($state === 'accepted')
                <div class="mb-6 rounded-lg border border-brand/30 bg-brand/10 p-4 text-sm">
                    <p class="font-semibold text-brand">{{ __('app.proposal_accepted_title') }}</p>
                    <p class="mt-1 text-white/70">{{ $quote->accepted_signer_name }} · {{ \App\Support\Format::dateTime($quote->accepted_at) }}</p>
                </div>
            @endif

            <h2 class="text-lg font-semibold">{{ __('app.proposal_title') }}</h2>
            <p class="mt-1 text-sm text-white/50">{{ __('app.proposal_intro') }}</p>

            <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                <div><div class="text-xs uppercase tracking-wide text-white/40">{{ __('app.company_name') }}</div><div class="text-sm">{{ $application->company_name }}</div></div>
                <div><div class="text-xs uppercase tracking-wide text-white/40">{{ __('app.us_dot_number') }}</div><div class="text-sm">{{ $application->us_dot_number ?: '—' }}</div></div>
                <div><div class="text-xs uppercase tracking-wide text-white/40">{{ __('app.effective_date') }}</div><div class="text-sm">{{ \App\Support\Format::date($quote->effective_date) }}</div></div>
                <div><div class="text-xs uppercase tracking-wide text-white/40">{{ __('app.proposal_valid_until') }}</div><div class="text-sm">{{ \App\Support\Format::date($quote->expires_at) }}</div></div>
            </div>

            <h3 class="{{ $sectionTitle }}">{{ __('app.proposal_coverages') }}</h3>
            <div class="overflow-x-auto rounded-lg border border-white/10">
                <table class="min-w-full divide-y divide-white/10">
                    <thead><tr>
                        <th class="{{ $th }}">{{ __('app.coverage') }}</th>
                        <th class="{{ $th }}">{{ __('app.limit') }}</th>
                        <th class="{{ $th }}">{{ __('app.deductible') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($quote->coverages as $line)
                            <tr>
                                <td class="{{ $td }}">{{ $line->displayName($locale) }}</td>
                                <td class="{{ $td }}">{{ $line->displayLimit() }}</td>
                                <td class="{{ $td }}">{{ $money($line->deductible) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="{{ $sectionTitle }}">{{ __('app.proposal_pricing') }}</h3>
            <div class="overflow-x-auto rounded-lg border border-white/10">
                <table class="min-w-full divide-y divide-white/10">
                    <tbody class="divide-y divide-white/5">
                        <tr><td class="{{ $td }}">{{ __('app.proposal_premium') }}</td><td class="{{ $td }} text-right">{{ $money($quote->carrier_premium) }}</td></tr>
                        <tr><td class="{{ $td }}">{{ __('app.proposal_fees') }}</td><td class="{{ $td }} text-right">{{ $money($quote->fees) }}</td></tr>
                        <tr><td class="{{ $td }}">{{ __('app.proposal_agency_fee') }}</td><td class="{{ $td }} text-right">{{ $money($quote->producer_fee) }}</td></tr>
                        <tr><td class="{{ $td }} font-semibold">{{ __('app.proposal_total_cost') }}</td><td class="{{ $td }} text-right font-semibold">{{ $money($quote->totalCost()) }}</td></tr>
                        <tr><td class="{{ $td }}">{{ __('app.down_payment') }}</td><td class="{{ $td }} text-right">{{ $money($quote->down_payment) }}</td></tr>
                        @if ($quote->number_of_payments)
                            <tr><td class="{{ $td }}">{{ __('app.proposal_payments') }}</td><td class="{{ $td }} text-right">{{ $quote->number_of_payments }} × {{ $money($quote->installment_amount) }}</td></tr>
                        @endif
                        <tr><td class="{{ $td }} font-semibold">{{ __('app.proposal_total_payable') }}</td><td class="{{ $td }} text-right text-base font-semibold text-brand">{{ $money($quote->totalPayable()) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-white/40">{{ __('app.proposal_estimate_note') }}</p>

            @if ($state === 'open')
                <div class="mt-8 border-t border-white/10 pt-6">
                    @include('livewire.partials.sign-block', [
                        'disclosureTitle' => __('app.proposal_disclosure_title'),
                        'disclosureBody' => __('app.proposal_disclosure_body'),
                        'disclosureAccept' => __('app.proposal_disclosure_accept'),
                    ])
                    <button wire:click="sign" class="{{ $btn }} mt-6 w-full sm:w-auto">{{ __('app.proposal_sign') }}</button>
                </div>
            @endif
        </div>
    @endif
</div>
