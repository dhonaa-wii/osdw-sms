@props(['code', 'title', 'message', 'icon'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }} · OSDW Scholarship System</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-body text-ink antialiased">
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-[720px] items-center px-4 py-4">
            <a href="{{ route('home') }}" class="font-display text-[16px] font-600 text-forest-700">OSDW Scholarship System</a>
        </div>
    </header>

    <main class="mx-auto flex min-h-[70vh] max-w-[560px] flex-col items-center justify-center px-4 py-12 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-forest-50 text-forest-700">
            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
        </span>

        <p class="mt-5 font-mono text-[15px] tracking-[0.05em] text-mist">Error {{ $code }}</p>
        <h1 class="mt-2 font-display text-[26px] font-600 text-ink">{{ $title }}</h1>
        <p class="mt-2 font-body text-[15px] text-slate">{{ $message }}</p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
