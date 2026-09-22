<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'OSDW Scholarship System' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-body text-ink antialiased">
    <div class="flex min-h-screen">
        <x-app.sidebar class="w-[260px] shrink-0" />

        <div class="flex-1">
            <main class="mx-auto max-w-[1400px] px-8 py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
