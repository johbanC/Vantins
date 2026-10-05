@php
    $links = [
        __('panel.quote.pdf_proposal') => [
            'es' => route('quotes.pdf', [$quote, 'proposal', 'es']),
            'en' => route('quotes.pdf', [$quote, 'proposal', 'en']),
        ],
    ];

    if ($quote->hasBinder()) {
        $links[__('panel.quote.pdf_binder')] = [
            'es' => route('quotes.pdf', [$quote, 'binder', 'es']),
            'en' => route('quotes.pdf', [$quote, 'binder', 'en']),
        ];
    }
@endphp

<div class="flex flex-col gap-5">
    @foreach ($links as $label => $urls)
        <div>
            <p class="mb-2 text-sm font-semibold">{{ $label }}</p>
            <div class="flex flex-wrap gap-3">
                <x-filament::button tag="a" :href="$urls['es']" target="_blank" icon="heroicon-o-document-arrow-down">{{ __('panel.action.pdf_es') }}</x-filament::button>
                <x-filament::button tag="a" :href="$urls['en']" target="_blank" color="gray" icon="heroicon-o-document-arrow-down">{{ __('panel.action.pdf_en') }}</x-filament::button>
            </div>
        </div>
    @endforeach

    @if ($quote->accepted_at)
        <div>
            <p class="mb-2 text-sm font-semibold">{{ __('panel.quote.pdf_signed') }}</p>
            <x-filament::button tag="a" :href="route('quotes.pdf', [$quote, 'signed'])" target="_blank" color="success" icon="heroicon-o-check-badge">{{ __('panel.quote.pdf_open') }}</x-filament::button>
        </div>
    @endif
</div>
