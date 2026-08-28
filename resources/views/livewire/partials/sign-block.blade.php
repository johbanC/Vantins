@php
    $input = 'w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder-white/30 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand';
    $label = 'mb-1 block text-xs font-medium uppercase tracking-wide text-white/60';
@endphp

<h3 class="mb-3 text-base font-semibold">{{ __('app.disclosure') }}</h3>
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
