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
    <header class="sticky top-0 z-10 border-b border-line bg-surface">
        <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-6 py-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="font-display text-[17px] font-600 text-navy-700">OSDW Scholarship System</a>
                @isset($openCount)
                    @if ($openCount > 0)
                        <span class="hidden items-center gap-1.5 rounded-full border border-line py-1 pl-1.5 pr-2.5 font-body text-[12px] font-500 text-slate sm:inline-flex">
                            <span class="relative flex size-1.5">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-navy-600 opacity-75 motion-reduce:animate-none"></span>
                                <span class="relative inline-flex size-1.5 rounded-full bg-navy-600"></span>
                            </span>
                            {{ $openCount }} open now
                        </span>
                    @endif
                @endisset
            </div>
            <nav class="flex flex-wrap items-center gap-4 font-body text-[14px] font-500 sm:gap-6">
                <a href="{{ route('programs.index') }}" class="text-ink hover:text-navy-700">Scholarship Programs</a>
                @auth
                    @if (auth()->user()->isStudent() && auth()->user()->applicant)
                        <livewire:student::notification-bell />
                    @endif
                    <a href="{{ auth()->user()->homeRoute() }}" class="rounded-control bg-navy-600 px-3 py-1.5 text-white hover:bg-navy-700">{{ auth()->user()->isStudent() ? 'My applications' : 'Dashboard' }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-slate hover:text-navy-700">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-control bg-navy-600 px-3 py-1.5 text-white hover:bg-navy-700">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto max-w-[1200px] px-6 py-10">
            @php $footerBannerUrl = \App\Models\BrandingImage::url(\App\Enums\BrandingImageKey::FooterBanner); @endphp
            @if ($footerBannerUrl)
                <img
                    src="{{ $footerBannerUrl }}"
                    alt="Student Welfare and Development, Cagayan State University Aparri Campus. Contact us: osdwaparri@csu.edu.ph, @OSDWAparri on Facebook, 0953-554-2328."
                    class="mb-10 w-full rounded-card border border-line"
                    loading="lazy"
                >
            @endif
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                <div>
                    <p class="font-display text-[16px] font-600 text-navy-700">OSDW Scholarship System</p>
                    <p class="mt-2 font-body text-[14px] text-slate">
                        Office of Student Development and Welfare
                        @isset($campus)
                            &mdash; {{ $campus->name }} campus
                        @endisset
                    </p>
                </div>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">For applicants</p>
                    <ul class="mt-2 space-y-1 font-body text-[14px]">
                        <li><a href="{{ route('programs.index') }}" class="text-ink hover:text-navy-700">Browse scholarship programs</a></li>
                        <li><a href="{{ route('results.index') }}" class="text-ink hover:text-navy-700">Published results</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Your account</p>
                    <ul class="mt-2 space-y-1 font-body text-[14px]">
                        <li><a href="{{ route('login') }}" class="text-ink hover:text-navy-700">Log in</a></li>
                        <li><a href="{{ route('register') }}" class="text-ink hover:text-navy-700">Register</a></li>
                    </ul>
                </div>
            </div>
            <p class="mt-8 border-t border-line pt-6 font-body text-[13px] text-mist">
                Students, staff and administrators all log in from the same page.
            </p>
        </div>
    </footer>
</body>
</html>
