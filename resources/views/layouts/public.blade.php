<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Vantins — {{ __('app.application_title') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                brand:  { DEFAULT: '#F59E0B', 600: '#D97706' },
                navy:   '#0A2452',
                'navy-dark': '#071F3D',
                'brand-gray': '#393A3D',
            } } }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    @livewireStyles
</head>
<body class="min-h-full bg-navy-dark text-white antialiased">
    <header class="border-b border-white/10 bg-black/40">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
            <img src="{{ asset('images/brand/logo-white.png') }}" alt="Vantins" class="h-8 w-auto">
            <span class="hidden text-xs uppercase tracking-widest text-white/50 sm:inline">Smart Protection Starts Here</span>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-3xl px-4 py-8 text-center text-xs text-white/40">
        +1 (754) 290-0308 · support@vantins.com · 28 W Flagler St Ste 300B #336 Miami, FL 33130 US
    </footer>

    @livewireScripts
</body>
</html>
