<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Help Center' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- "Download PDF" prints this page: the chrome drops out, a cover and contents page appear, and each chapter starts a new A4 page. --}}
    <style>
        [id] { scroll-margin-top: 5rem; }
        figure[data-help-screen] { counter-increment: help-figure; }
        [data-help-figure-no]::before { content: "Figure " counter(help-figure) "."; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 14mm 13mm 16mm;
                @bottom-left { content: "OSDW Help Center"; font-family: 'Public Sans', sans-serif; font-size: 8pt; }
                @bottom-right { content: "Page " counter(page) " of " counter(pages); font-family: 'Public Sans', sans-serif; font-size: 8pt; }
            }
            @page :first {
                @bottom-left { content: none; }
                @bottom-right { content: none; }
            }
            html, body { background: #fff !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 12px; }
            [data-help-chrome] { display: none !important; }
            [data-help-print] { display: block !important; }
            [data-help-layout] { display: block !important; max-width: none !important; padding: 0 !important; }
            [data-help-chapter] { break-before: page; }
            [data-help-topic] { padding-top: 0 !important; }
            [data-help-screen], [data-help-keep], table, figure { break-inside: avoid; }
            h2, h3 { break-after: avoid; }
            a { color: inherit !important; text-decoration: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-paper font-body text-ink antialiased">
    @php
        $logoUrl = \App\Models\BrandingImage::url(\App\Enums\BrandingImageKey::Logo);
        $user = auth()->user();
        $back = match (true) {
            $user === null => ['url' => route('home'), 'label' => 'Home'],
            $user->isStudent() && $user->applicant => ['url' => route('student.applications.index'), 'label' => 'My applications'],
            $user->isStudent() => ['url' => route('student.profile.complete'), 'label' => 'Set up profile'],
            default => ['url' => route('dashboard'), 'label' => 'Dashboard'],
        };
    @endphp

    <header data-help-chrome class="sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-[1240px] items-center gap-3 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="size-8 shrink-0 rounded-full object-contain">
                @else
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-navy-600 text-white">
                        <x-ui.icon name="graduation" class="size-4" />
                    </span>
                @endif
                <span class="hidden truncate font-display text-[15px] font-600 text-navy-700 sm:inline">OSDW Scholarships</span>
            </a>
            <span class="hidden h-5 w-px bg-line sm:block" aria-hidden="true"></span>
            <span class="flex items-center gap-1.5 font-display text-[15px] font-600 text-ink">
                <x-ui.icon name="help" class="size-[18px] text-navy-600" />
                Help Center
            </span>

            <div class="ml-auto flex items-center gap-1.5">
                <a href="{{ $back['url'] }}" class="inline-flex h-9 items-center gap-1.5 rounded-control px-2.5 font-body text-[13px] font-500 text-slate transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                    <x-ui.icon name="arrow-right" class="size-4 rotate-180" />
                    <span class="hidden sm:inline">{{ $back['label'] }}</span>
                    <span class="sr-only sm:hidden">Back to {{ $back['label'] }}</span>
                </a>
                {{-- Fetches the server-made PDF and saves it, so the button can show progress and stays on the page if it fails. --}}
                <button
                    type="button"
                    x-data="{
                        state: 'idle',
                        async download() {
                            if (this.state === 'busy') return;
                            this.state = 'busy';
                            try {
                                const response = await fetch(@js(route('help.download')));
                                if (! response.ok) throw new Error(response.status);
                                const link = document.createElement('a');
                                link.href = URL.createObjectURL(await response.blob());
                                link.download = 'OSDW Help Center.pdf';
                                document.body.appendChild(link);
                                link.click();
                                link.remove();
                                setTimeout(() => URL.revokeObjectURL(link.href), 10000);
                                this.state = 'idle';
                            } catch (error) {
                                this.state = 'failed';
                                setTimeout(() => { this.state = 'idle'; }, 4000);
                            }
                        },
                    }"
                    x-on:click="download()"
                    x-bind:disabled="state === 'busy'"
                    x-bind:aria-busy="(state === 'busy').toString()"
                    x-bind:class="state === 'failed' ? 'bg-crimson-600 hover:bg-crimson-700' : 'bg-navy-600 hover:bg-navy-700'"
                    class="inline-flex h-9 items-center gap-1.5 rounded-control px-3 font-body text-[13px] font-500 text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2 disabled:cursor-wait"
                >
                    <span x-show="state !== 'busy'"><x-ui.icon name="download" class="size-4" /></span>
                    <span x-cloak x-show="state === 'busy'"><x-ui.spinner /></span>
                    <span class="hidden sm:inline" x-text="{ idle: 'Download PDF', busy: 'Preparing PDF…', failed: 'Couldn’t download. Try again' }[state]">Download PDF</span>
                    <span class="sm:hidden" x-text="state === 'failed' ? 'Retry' : 'PDF'">PDF</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>
</body>
</html>
