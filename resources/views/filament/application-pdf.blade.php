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
</div>
