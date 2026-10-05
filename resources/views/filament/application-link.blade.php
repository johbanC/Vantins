@php
    $status = $application->linkStatus();
    $usable = in_array($status, ['active', 'completed'], true);
@endphp

<div class="flex flex-col gap-4" x-data="{ copied: false, copiedPin: false, url: @js($url), pin: @js($application->link_pin) }">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.action.share_hint') }}
    </p>

    <p class="text-sm">
        <span class="font-semibold">{{ __('panel.link.status') }}:</span>
        {{ __('panel.link.states.'.$status) }}
        @if ($status === 'active' && $application->link_expires_at)
            · {{ __('panel.link.expires') }} {{ \App\Support\Format::date($application->link_expires_at) }}
        @endif
    </p>

    @if ($usable)
        <x-filament::input.wrapper>
            <x-filament::input type="text" readonly x-bind:value="url" />
        </x-filament::input.wrapper>

        <div class="flex flex-wrap gap-3">
            <x-filament::button
                icon="heroicon-o-clipboard"
                x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1500)"
            >
                <span x-show="!copied">{{ __('panel.action.copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('panel.action.copied') }}</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                :href="$url"
                target="_blank"
                color="gray"
                icon="heroicon-o-arrow-top-right-on-square"
            >
                {{ __('panel.action.open_new_tab') }}
            </x-filament::button>
        </div>

        <div class="rounded-lg border border-gray-300 p-3 dark:border-white/20">
            <p class="text-sm font-semibold">{{ __('panel.link.pin') }}</p>
            <p class="mt-1 font-mono text-2xl tracking-widest">{{ $application->link_pin }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('panel.link.pin_hint') }}
                @unless ($application->drivers()->exists())
                    {{ __('panel.link.pin_none') }}
                @endunless
            </p>
            <x-filament::button class="mt-2" size="xs" color="gray" x-on:click="navigator.clipboard.writeText(pin); copiedPin = true; setTimeout(() => copiedPin = false, 1500)">
                <span x-show="!copiedPin">{{ __('panel.action.copy') }} PIN</span>
                <span x-show="copiedPin" x-cloak>{{ __('panel.action.copied') }}</span>
            </x-filament::button>
        </div>
    @endif
</div>
