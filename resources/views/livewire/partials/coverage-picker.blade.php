@php
    $units = fn (array $rows) => collect($rows)
        ->filter(fn ($r) => ! empty($r['id']))
        ->mapWithKeys(fn ($r) => [(string) $r['id'] => trim(($r['year'] ?? '').' '.($r['make'] ?? '').' '.($r['vin'] ?? ''))]);
    $vehicleChoices = $units($vehicles);
    $trailerChoices = $units($trailers);
    $selectedNames = collect($coverages)->map(fn ($r) => optional($types->get($r['type'] ?? null))->name())->filter()->values();
@endphp

<h2 class="mb-2 text-lg font-semibold">{{ __('app.coverage_pick_title') }}</h2>
<p class="mb-4 text-sm text-white/50">{{ __('app.coverage_pick_hint') }}</p>

{{-- The catalog: one chip per coverage, ticked = selected. --}}
<div class="mb-2 flex flex-wrap gap-2">
    @foreach ($catalog as $key => $type)
        @if ($type->isRepeatable())
            <button type="button" wire:click="toggleCoverage('{{ $key }}')" class="{{ $btnGhost }} !py-2">{{ __('app.coverage_add_other') }}</button>
        @else
            @php $selected = collect($coverages)->contains(fn ($r) => ($r['type'] ?? null) === $key); @endphp
            <button type="button" wire:click="toggleCoverage('{{ $key }}')"
                    class="rounded-full border px-4 py-2 text-sm {{ $selected ? 'border-brand bg-brand font-semibold text-navy-dark' : 'border-white/20 text-white hover:bg-white/10' }}">
                {{ $selected ? '✓ ' : '' }}{{ $type->name() }}
            </button>
        @endif
    @endforeach
</div>
<p class="mb-6 text-xs text-white/40">{{ __('app.coverage_intent_note') }}</p>

<div class="space-y-4">
    @forelse ($coverages as $i => $row)
        @php
            $type = $types->get($row['type'] ?? null);
        @endphp
        @continue(! $type)

        <div class="rounded-xl border border-white/10 bg-white/5 p-4" wire:key="coverage-{{ $row['id'] ?? 'new' }}-{{ $i }}">
            <div class="mb-3 flex items-center justify-between gap-3">
                <span class="font-semibold">
                    {{ $type->isRepeatable() ? (($row['custom_name'] ?? null) ?: $type->name()) : $type->name() }}
                    @if ($type->isRepeatable())
                        <span class="ml-2 rounded bg-brand/20 px-2 py-0.5 text-xs font-normal text-brand">{{ __('app.coverage_review_flag') }}</span>
                    @endif
                </span>
                <button type="button" wire:click="removeRow('coverages', {{ $i }})" class="text-xs text-red-300 hover:text-red-200">{{ __('app.remove') }}</button>
            </div>
            @error('coverages.'.$i.'.type') <p class="mb-2 text-xs text-red-300">{{ $message }}</p> @enderror

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($type->fields() as $field)
                    @switch($field)
                        @case('custom_name')
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">{{ __('app.custom_name') }}</label>
                                <input maxlength="190" wire:model.blur="coverages.{{ $i }}.custom_name" class="{{ $input }} @error('coverages.'.$i.'.custom_name') border-red-400 @enderror">
                                @error('coverages.'.$i.'.custom_name') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            @break

                        @case('limit')
                        @case('aggregate')
                        @case('deductible')
                            @php
                                $column = ['limit' => 'limit_amount', 'aggregate' => 'aggregate_limit', 'deductible' => 'deductible'][$field];
                                $options = $type->options($field);
                            @endphp
                            <div>
                                <label class="{{ $label }}">{{ __('app.'.$field) }}</label>
                                @if ($options)
                                    <select wire:model.blur="coverages.{{ $i }}.{{ $column }}" class="{{ $input }} @error('coverages.'.$i.'.'.$column) border-red-400 @enderror">
                                        <option value="">{{ __('app.coverage_choose') }}</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}" class="text-black">${{ number_format($option) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="number" min="0" step="0.01" placeholder="{{ __('app.coverage_manual_hint') }}" wire:model.blur="coverages.{{ $i }}.{{ $column }}" class="{{ $input }} @error('coverages.'.$i.'.'.$column) border-red-400 @enderror">
                                @endif
                                @error('coverages.'.$i.'.'.$column) <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            @break

                        @case('underlying')
                            <div>
                                <label class="{{ $label }}">{{ __('app.underlying_coverage') }}</label>
                                <input list="underlying-{{ $i }}" maxlength="190" wire:model.blur="coverages.{{ $i }}.underlying_coverage" class="{{ $input }} @error('coverages.'.$i.'.underlying_coverage') border-red-400 @enderror">
                                <datalist id="underlying-{{ $i }}">
                                    @foreach ($selectedNames as $name)
                                        @unless ($name === $type->name())<option value="{{ $name }}">@endunless
                                    @endforeach
                                </datalist>
                                @error('coverages.'.$i.'.underlying_coverage') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            @break

                        @case('vehicles')
                        @case('trailers')
                            @php
                                $choices = $field === 'vehicles' ? $vehicleChoices : $trailerChoices;
                                $column = $field === 'vehicles' ? 'vehicle_ids' : 'trailer_ids';
                            @endphp
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">{{ __('app.coverage_units') }}: {{ __('app.'.$field.'_schedule') }}</label>
                                @forelse ($choices as $id => $text)
                                    <label class="mr-4 inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" value="{{ $id }}" wire:model.live="coverages.{{ $i }}.{{ $column }}" class="h-4 w-4 rounded border-white/30 bg-white/10 text-brand">
                                        {{ $text ?: '#'.$id }}
                                    </label>
                                @empty
                                    <p class="text-sm text-white/40">{{ __('app.none_yet') }}</p>
                                @endforelse
                                @error('coverages.'.$i.'.'.$column) <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            @break

                        @case('notes')
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">{{ __('app.notes') }}</label>
                                <textarea rows="2" maxlength="1000" wire:model.blur="coverages.{{ $i }}.notes" class="{{ $input }}"></textarea>
                                @error('coverages.'.$i.'.notes') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            @break
                    @endswitch
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-sm text-white/40">{{ __('app.coverage_none') }}</p>
    @endforelse
</div>
