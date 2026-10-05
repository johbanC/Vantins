@php
    $status = $quote->acceptanceStatus();
    $url = $quote->acceptanceUrl();
@endphp

<div class="space-y-3 text-sm" x-data="{ copied: false }">
    <p class="text-gray-500 dark:text-gray-400">{{ __('panel.quote.link_copy_hint') }}</p>

    <p>
        <span class="font-semibold">{{ __('panel.quote.link_status') }}:</span>
        {{ __('panel.quote.link_states.'.$status) }}
        @if ($quote->acceptance_expires_at)
            · {{ __('panel.quote.link_expires') }} {{ \App\Support\Format::date($quote->acceptance_expires_at) }}
        @endif
    </p>

    @if ($url && $status === 'open')
        <div class="break-all rounded-lg border border-gray-300 p-3 dark:border-white/20">{{ $url }}</div>
        <div class="flex gap-2">
            <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText(@js($url)); copied = true">
                <span x-show="! copied">{{ __('panel.action.copy') }}</span>
                <span x-show="copied" x-cloak>&check;</span>
            </x-filament::button>
            <x-filament::button size="sm" tag="a" href="{{ $url }}" target="_blank">{{ __('panel.action.open_new_tab') }}</x-filament::button>
        </div>
    @endif
</div>
