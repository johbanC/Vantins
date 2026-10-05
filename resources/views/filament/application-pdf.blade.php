<div class="flex flex-col gap-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.action.pdf_hint') }}
    </p>

    <div class="flex flex-wrap gap-3">
        <x-filament::button
            tag="a"
            :href="$es"
            target="_blank"
            icon="heroicon-o-document-arrow-down"
        >
            {{ __('panel.action.pdf_es') }}
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="$en"
            target="_blank"
            color="gray"
            icon="heroicon-o-document-arrow-down"
        >
            {{ __('panel.action.pdf_en') }}
        </x-filament::button>
    </div>

    @if (! empty($signed))
        <div>
            <p class="mb-2 text-sm font-semibold">{{ __('panel.quote.pdf_application_signed') }}</p>
            <x-filament::button tag="a" :href="$signed" target="_blank" color="success" icon="heroicon-o-check-badge">
                {{ __('panel.quote.pdf_open') }}
            </x-filament::button>
        </div>
    @endif
</div>
