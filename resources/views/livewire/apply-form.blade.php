@php
    $input = 'w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/30 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand';
    $label = 'mb-1 block text-xs font-medium uppercase tracking-wide text-white/60';
    $btn = 'rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-navy-dark hover:bg-brand-600';
    $btnGhost = 'rounded-lg border border-white/20 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10';
@endphp

<div class="space-y-6" x-data>
    {{-- Locale switch --}}
    <div class="flex justify-end gap-2 text-xs">
        <button wire:click="switchLocale('en')" class="rounded px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-brand text-navy-dark' : 'bg-white/10' }}">EN</button>
        <button wire:click="switchLocale('es')" class="rounded px-2 py-1 {{ app()->getLocale() === 'es' ? 'bg-brand text-navy-dark' : 'bg-white/10' }}">ES</button>
    </div>

    @if ($step <= $totalSteps)
        <div>
            <div class="mb-2 flex justify-between text-xs text-white/50">
                <span>{{ __('app.step') }} {{ $step }} / {{ $totalSteps }}</span>
                <span>{{ $application->company_name ?: __('app.new_application') }}</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                <div class="h-full bg-brand transition-all" style="width: {{ ($step / $totalSteps) * 100 }}%"></div>
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-black/30 p-5 sm:p-8">

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

                <div class="mt-4 border-t border-white/10 pt-4">
                    <label class="{{ $label }}">{{ __('app.total_policy_premium') }}</label>
                    <input type="number" wire:model="form.total_policy_premium" class="{{ $input }} sm:max-w-xs">
                </div>
            </div>
        @endif

        @if ($step === 6)
            <h2 class="mb-5 text-lg font-semibold">{{ __('app.agency') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="{{ $label }}">{{ __('app.agency_name') }}</label><input wire:model="form.agency_name" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">{{ __('app.agency_phone') }}</label><input wire:model="form.agency_phone" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">{{ __('app.contact_agent_name') }}</label><input wire:model="form.contact_agent_name" class="{{ $input }}"></div>
            </div>
        @endif

        @if ($step === 7)
            <h2 class="mb-5 text-lg font-semibold">{{ __('app.disclosure') }}</h2>
            <p class="mb-4 text-sm leading-relaxed text-white/70">{{ __('app.disclosure_body') }}</p>

            <label class="mb-4 flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model="disclosureAccepted" class="mt-1 h-4 w-4 rounded border-white/30 bg-white/10 text-brand">
                <span>{{ __('app.disclosure_accept') }}</span>
            </label>
            @error('disclosureAccepted') <p class="mb-3 text-xs text-red-300">{{ $message }}</p> @enderror

            <div class="mb-4">
                <label class="{{ $label }}">{{ __('app.signer_name') }}</label>
                <input wire:model="signerName" class="{{ $input }} sm:max-w-sm">
                @error('signerName') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
            </div>

            <div wire:ignore
                 x-data="{
                    pad: null,
                    init() {
                        const c = this.$refs.canvas;
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                        c.width = c.offsetWidth * ratio; c.height = c.offsetHeight * ratio;
                        c.getContext('2d').scale(ratio, ratio);
                        this.pad = new SignaturePad(c, { penColor: '#0A2452', backgroundColor: '#ffffff' });
                        this.pad.addEventListener('endStroke', () => @this.set('signatureData', this.pad.toDataURL('image/png')));
                    },
                    clear() { this.pad.clear(); @this.set('signatureData', null); }
                 }">
                <label class="{{ $label }}">{{ __('app.signature') }}</label>
                <canvas x-ref="canvas" class="h-40 w-full touch-none rounded-lg border border-white/20 bg-white"></canvas>
                <button type="button" @click="clear()" class="mt-2 text-xs text-white/50 hover:text-white">{{ __('app.clear') }}</button>
            </div>
            @error('signatureData') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
        @endif

        @if ($step > $totalSteps)
            <div class="py-8 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-brand text-navy-dark">&check;</div>
                <h2 class="text-lg font-semibold">{{ __('app.thanks_title') }}</h2>
                <p class="mt-2 text-sm text-white/60">{{ __('app.thanks_body') }}</p>
            </div>
        @endif
    </div>

    @if ($step <= $totalSteps)
        <div class="flex items-center justify-between">
            <button wire:click="back" @class([$btnGhost, 'invisible' => $step === 1])>{{ __('app.back') }}</button>
            <span wire:loading class="text-xs text-white/40">{{ __('app.saving') }}…</span>
            @if ($step < $totalSteps)
                <button wire:click="next" class="{{ $btn }}">{{ __('app.next') }}</button>
            @else
                <button wire:click="submit" class="{{ $btn }}">{{ __('app.submit') }}</button>
            @endif
        </div>
    @endif
</div>
