@php
    $input = 'w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/30 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand';
    $label = 'mb-1 block text-xs font-medium uppercase tracking-wide text-white/60';
    $btn = 'rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-navy-dark hover:bg-brand-600';
    $btnGhost = 'rounded-lg border border-white/20 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10';
    $card = 'rounded-2xl border border-white/10 bg-black/30 p-5 sm:p-8';
@endphp

<div class="space-y-6" x-data>

    {{-- Locale switch --}}
    <div class="flex justify-end gap-2 text-xs">
        <button wire:click="switchLocale('en')" class="rounded px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-brand text-navy-dark' : 'bg-white/10' }}">EN</button>
        <button wire:click="switchLocale('es')" class="rounded px-2 py-1 {{ app()->getLocale() === 'es' ? 'bg-brand text-navy-dark' : 'bg-white/10' }}">ES</button>
    </div>

    {{-- ===== DONE: signed ===== --}}
    @if ($done === 'signed')
        <div class="{{ $card }} py-10 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-brand text-navy-dark">&check;</div>
            <h2 class="text-lg font-semibold">{{ __('app.thanks_title') }}</h2>
            <p class="mt-2 text-sm text-white/60">{{ __('app.thanks_body') }}</p>
        </div>

    {{-- ===== DONE: advisor saved (no signature yet) ===== --}}
    @elseif ($done === 'saved')
        <div class="{{ $card }} text-center">
            <h2 class="text-lg font-semibold">{{ __('app.saved_title') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-white/60">{{ __('app.saved_body') }}</p>
            <div class="mx-auto mt-4 max-w-md break-all rounded-lg border border-white/15 bg-white/5 p-3 text-xs text-brand">
                {{ route('apply.show', $application->token) }}
            </div>
        </div>

    {{-- ===== Already signed ===== --}}
    @elseif ($locked)
        <div class="{{ $card }} space-y-6">
            <div class="rounded-lg border border-brand/30 bg-brand/10 p-4 text-sm">
                <p class="font-semibold text-brand">{{ __('app.already_signed_title') }}</p>
                <p class="mt-1 text-white/70">
                    {{ __('app.signer_name') }}: {{ $application->signer_name }} &nbsp;·&nbsp;
                    {{ optional($application->disclosure_accepted_at)->format('m/d/Y H:i') }}
                </p>
            </div>
            @include('livewire.partials.apply-review')
        </div>

    {{-- ===== Advisor: editable multi-step form ===== --}}
    @elseif ($editable)
        <div>
            <div class="mb-2 flex justify-between text-xs text-white/50">
                <span>{{ __('app.step') }} {{ $step }} / {{ $totalSteps }}</span>
                <span>{{ $application->company_name ?: __('app.new_application') }}</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                <div class="h-full bg-brand transition-all" style="width: {{ ($step / $totalSteps) * 100 }}%"></div>
            </div>
        </div>

        <div class="{{ $card }}">
            @if ($step === 1)
                <h2 class="mb-5 text-lg font-semibold">{{ __('app.applicant_information') }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ([
                        'company_name' => 'text', 'company_representative' => 'text',
                        'phone_number' => 'tel', 'email' => 'email',
                        'mailing_address' => 'text', 'parking_address' => 'text',
                        'effective_date' => 'date', 'us_dot_number' => 'text',
                        'radius_of_operations' => 'text', 'years_in_business' => 'text',
                        'power_units' => 'number',
                    ] as $field => $type)
                        <div>
                            <label class="{{ $label }}">{{ __('app.'.$field) }}</label>
                            <input type="{{ $type }}" wire:model="form.{{ $field }}" class="{{ $input }}">
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">{{ __('app.commodities_hauled') }}</label>
                        <textarea wire:model="form.commodities_hauled" rows="2" class="{{ $input }}"></textarea>
                    </div>
                </div>
            @endif

            @foreach ([2 => 'drivers', 3 => 'vehicles', 4 => 'trailers'] as $s => $col)
                @if ($step === $s)
                    <h2 class="mb-5 text-lg font-semibold">{{ __('app.'.$col.'_schedule') }}</h2>
                    <div class="space-y-4">
                        @forelse ($$col as $i => $row)
                            <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs text-white/50">#{{ $i + 1 }}</span>
                                    <button wire:click="removeRow('{{ $col }}', {{ $i }})" class="text-xs text-red-300 hover:text-red-200">{{ __('app.remove') }}</button>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    @if ($col === 'drivers')
                                        <div class="sm:col-span-2"><label class="{{ $label }}">{{ __('app.driver_name') }}</label><input wire:model="drivers.{{ $i }}.driver_name" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.dob') }}</label><input type="date" wire:model="drivers.{{ $i }}.dob" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.cdl_number') }}</label><input wire:model="drivers.{{ $i }}.cdl_number" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.state_issued') }}</label><input maxlength="40" wire:model="drivers.{{ $i }}.state_issued" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.experience') }}</label><input maxlength="60" wire:model="drivers.{{ $i }}.experience" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.date_of_hire') }}</label><input type="date" wire:model="drivers.{{ $i }}.date_of_hire" class="{{ $input }}"></div>
                                    @else
                                        <div><label class="{{ $label }}">{{ __('app.year') }}</label><input maxlength="4" inputmode="numeric" wire:model="{{ $col }}.{{ $i }}.year" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.make') }}</label><input maxlength="120" wire:model="{{ $col }}.{{ $i }}.make" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.vin') }}</label><input maxlength="17" wire:model="{{ $col }}.{{ $i }}.vin" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.body_type') }}</label><input maxlength="120" wire:model="{{ $col }}.{{ $i }}.body_type" class="{{ $input }}"></div>
                                        <div><label class="{{ $label }}">{{ __('app.stated_value') }}</label><input type="number" wire:model="{{ $col }}.{{ $i }}.stated_value" class="{{ $input }}"></div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-white/40">{{ __('app.none_yet') }}</p>
                        @endforelse
                        <button wire:click="addRow('{{ $col }}')" class="{{ $btnGhost }}">+ {{ __('app.add') }}</button>
                    </div>
                @endif
            @endforeach

            @if ($step === 5)
                <h2 class="mb-5 text-lg font-semibold">{{ __('app.coverages_list') }}</h2>
                <div class="space-y-4">
                    @forelse ($coverages as $i => $row)
                        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs text-white/50">#{{ $i + 1 }}</span>
                                <button wire:click="removeRow('coverages', {{ $i }})" class="text-xs text-red-300 hover:text-red-200">{{ __('app.remove') }}</button>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                <div><label class="{{ $label }}">{{ __('app.coverage') }}</label><input wire:model="coverages.{{ $i }}.coverage" class="{{ $input }}"></div>
                                <div><label class="{{ $label }}">{{ __('app.limit') }}</label><input wire:model="coverages.{{ $i }}.limit_amount" class="{{ $input }}"></div>
                                <div><label class="{{ $label }}">{{ __('app.deductible') }}</label><input wire:model="coverages.{{ $i }}.deductible" class="{{ $input }}"></div>
                                <div><label class="{{ $label }}">{{ __('app.premium') }}</label><input type="number" wire:model="coverages.{{ $i }}.premium" class="{{ $input }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-white/40">{{ __('app.none_yet') }}</p>
                    @endforelse
                    <button wire:click="addRow('coverages')" class="{{ $btnGhost }}">+ {{ __('app.add') }}</button>
                </div>
            @endif

            @if ($step === 6)
                @php
                    $down = (float) ($form['down_payment'] ?? 0);
                    $monthly = (float) ($form['monthly_payment'] ?? 0);
                    $n = max((int) ($form['number_of_payments'] ?? 0), 0);
                    $planTotal = $down + $monthly * $n;
                @endphp
                <h2 class="mb-5 text-lg font-semibold">{{ __('app.finance_proposal') }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label class="{{ $label }}">{{ __('app.down_payment') }}</label><input type="number" step="0.01" wire:model="form.down_payment" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">{{ __('app.number_of_payments') }}</label><input type="number" wire:model="form.number_of_payments" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">{{ __('app.monthly_payment') }}</label><input type="number" step="0.01" wire:model="form.monthly_payment" class="{{ $input }}"></div>
                </div>
                <div class="mt-4 border-t border-white/10 pt-4 text-sm">
                    <div class="flex justify-between text-white/60">
                        <span>{{ __('app.total_policy_premium') }}</span>
                        <span class="text-lg font-semibold text-brand">${{ number_format($planTotal, 2) }}</span>
                    </div>
                </div>
            @endif

            @if ($step === 7)
                <h2 class="mb-5 text-lg font-semibold">{{ __('app.review_title') }}</h2>
                <p class="mb-5 text-sm text-white/50">{{ __('app.review_advisor_hint') }}</p>
                @include('livewire.partials.apply-review')

                <div class="mt-8 border-t border-white/10 pt-6">
                    @include('livewire.partials.sign-block')
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between">
            <button wire:click="back" @class([$btnGhost, 'invisible' => $step === 1])>{{ __('app.back') }}</button>
            <span wire:loading class="text-xs text-white/40">{{ __('app.saving') }}…</span>
            @if ($step < $totalSteps)
                <button wire:click="next" class="{{ $btn }}">{{ __('app.next') }}</button>
            @else
                <div class="flex gap-2">
                    <button wire:click="saveDraft" class="{{ $btnGhost }}">{{ __('app.save_no_sign') }}</button>
                    <button wire:click="sign" class="{{ $btn }}">{{ __('app.sign_send') }}</button>
                </div>
            @endif
        </div>

    {{-- ===== Client: read-only review + sign ===== --}}
    @else
        <div class="{{ $card }}">
            <h2 class="mb-2 text-lg font-semibold">{{ __('app.review_title') }}</h2>
            <p class="mb-6 text-sm text-white/50">{{ __('app.review_client_hint') }}</p>
            @include('livewire.partials.apply-review')

            <div class="mt-8 border-t border-white/10 pt-6">
                @include('livewire.partials.sign-block')
                <button wire:click="sign" class="{{ $btn }} mt-6 w-full sm:w-auto">{{ __('app.sign_send') }}</button>
            </div>
        </div>
    @endif
</div>
