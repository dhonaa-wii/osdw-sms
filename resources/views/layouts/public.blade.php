<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'OSDW Scholarship System' }}</title>
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-body text-ink antialiased">
    <header class="sticky top-0 z-10 border-b border-line bg-surface">
        <div class="mx-auto flex max-w-[720px] flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a href="{{ route('home') }}" class="font-display text-[16px] font-600 text-forest-700">OSDW Scholarship System</a>
            <nav class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <a href="{{ route('programs.index') }}" class="font-body text-[13px] text-slate hover:text-forest-700">Browse</a>
                <a href="{{ route('track.lookup') }}" class="font-body text-[13px] text-slate hover:text-forest-700">Track</a>
                <a href="{{ route('results.index') }}" class="font-body text-[13px] text-slate hover:text-forest-700">Results</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-[720px] px-4 py-12">
        {{ $slot }}
    </main>
</body>
</html>
