<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vantins — Document verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-full items-center justify-center bg-[#071F3D] p-6 text-white">
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-black/30 p-8 text-center">
        <div class="mb-4 flex justify-center">
            <img src="{{ asset('images/brand/logo-white.png') }}" alt="Vantins" class="h-9 w-auto">
        </div>

        @if ($application)
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-2xl">&check;</div>
            <h1 class="text-lg font-semibold">Valid document</h1>
            <p class="mt-2 text-sm text-white/60">
                This is a genuine Vantins Insurance Agency document.
            </p>
            <dl class="mt-5 space-y-1 text-left text-sm">
                <div class="flex justify-between border-b border-white/10 py-1"><dt class="text-white/50">Reference</dt><dd>{{ $application->verification_code }}</dd></div>
                <div class="flex justify-between border-b border-white/10 py-1"><dt class="text-white/50">Company</dt><dd>{{ $application->company_name ?: '—' }}</dd></div>
                <div class="flex justify-between border-b border-white/10 py-1"><dt class="text-white/50">Status</dt><dd class="uppercase">{{ str_replace('_', ' ', $application->status) }}</dd></div>
                <div class="flex justify-between py-1"><dt class="text-white/50">Issued</dt><dd>{{ $application->created_at->format('M d, Y') }}</dd></div>
            </dl>
        @else
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-500 text-2xl">&times;</div>
            <h1 class="text-lg font-semibold">Not a valid document</h1>
            <p class="mt-2 text-sm text-white/60">
                Code <span class="font-mono">{{ $code }}</span> does not match any document issued by our company.
            </p>
        @endif

        <p class="mt-6 text-xs text-white/40">+1 (754) 290-0308 · support@vantins.com</p>
    </div>
</body>
</html>
