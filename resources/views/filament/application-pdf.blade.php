<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.action.pdf_hint') }}
    </p>
    <div class="flex flex-col gap-2 sm:flex-row">
        <a href="{{ $es }}" target="_blank"
           class="flex-1 rounded-lg bg-amber-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-600">
            {{ __('panel.action.pdf_es') }}
        </a>
        <a href="{{ $en }}" target="_blank"
           class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
            {{ __('panel.action.pdf_en') }}
        </a>
    </div>
</div>
