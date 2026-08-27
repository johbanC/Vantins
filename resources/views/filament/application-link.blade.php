<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Comparte este enlace con el cliente para que llene la solicitud desde cualquier dispositivo.
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
            <span x-show="!copied">Copiar</span>
            <span x-show="copied" x-cloak>¡Copiado!</span>
        </button>
    </div>
    <a href="{{ $url }}" target="_blank" class="inline-block text-xs text-amber-600 hover:underline">Abrir en una pestaña nueva &rarr;</a>
</div>
