<div class="flex flex-col gap-4" x-data="{ copied: false, url: @js($url) }">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.action.share_hint') }}
    </p>

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
</div>
