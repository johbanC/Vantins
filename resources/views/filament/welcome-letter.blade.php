<div class="flex flex-col gap-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.action.welcome_letter_hint') }}
    </p>

    @if ($sentAt)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.action.welcome_letter_sent_on', ['date' => $sentAt->format('Y-m-d H:i')]) }}
        </p>
    @endif

    <div class="flex flex-wrap gap-3">
        <x-filament::button
            tag="a"
            :href="$es"
            target="_blank"
            icon="heroicon-o-envelope"
        >
            {{ __('panel.action.pdf_es') }}
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="$en"
            target="_blank"
            color="gray"
            icon="heroicon-o-envelope"
        >
            {{ __('panel.action.pdf_en') }}
        </x-filament::button>
    </div>
</div>
