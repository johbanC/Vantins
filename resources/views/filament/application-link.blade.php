<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.action.share_hint') }}
    </p>
    <div
        x-data="{ copied: false, url: @js($url) }"
        class="flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900"
    >
        <input type="text" readonly :value="url"
               class="w-full bg-transparent text-sm text-gray-700 outline-none dark:text-gray-200">
        <button type="button"
                x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1500)"
                class="shrink-0 rounded-md bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600">
            <span x-show="!copied">{{ __('panel.action.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('panel.action.copied') }}</span>
        </button>
    </div>
    <a href="{{ $url }}" target="_blank" class="inline-block text-xs text-amber-600 hover:underline">{{ __('panel.action.open_new_tab') }} &rarr;</a>
</div>
