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
    @php
        $logoUrl = \App\Models\BrandingImage::url(\App\Enums\BrandingImageKey::Logo);
        $user = auth()->user();
        $student = $user?->isStudent() && $user->applicant;
        $onResults = request()->routeIs('results.*', 'programs.results');
        $navLinks = array_filter([
            ['route' => 'programs.index', 'active' => ! $onResults && request()->routeIs('programs.*'), 'label' => 'Browse', 'icon' => 'search'],
            $student ? ['route' => 'student.applications.index', 'active' => request()->routeIs('student.applications.*'), 'label' => 'My applications', 'icon' => 'form'] : null,
            ['route' => 'results.index', 'active' => $onResults, 'label' => 'Results', 'icon' => 'award'],
        ]);
    @endphp

    <header class="sticky top-0 z-20 border-b border-line bg-surface/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-[1040px] items-center justify-between gap-3 px-4">
            <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 items-center gap-2.5 rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="size-8 shrink-0 rounded-full object-contain">
                @else
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-navy-600 text-white">
                        <x-ui.icon name="graduation" class="size-4" />
                    </span>
                @endif
                <span class="truncate font-display text-[15px] font-600 text-navy-700">OSDW Scholarships</span>
            </a>

            <div class="flex items-center gap-1">
                <nav class="flex items-center gap-1" aria-label="Main">
                    @foreach ($navLinks as $link)
                        @php $active = $link['active']; @endphp
                        <a
                            href="{{ route($link['route']) }}"
                            wire:navigate
                            @if ($active) aria-current="page" @endif
                            title="{{ $link['label'] }}"
                            class="inline-flex h-9 items-center gap-1.5 rounded-control px-2.5 font-body text-[13px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 sm:px-3 {{ $active ? 'bg-navy-50 text-navy-700' : 'text-slate hover:bg-paper hover:text-ink' }}"
                        >
                            <x-ui.icon :name="$link['icon']" class="size-4" />
                            <span class="{{ $student ? 'hidden md:inline' : '' }}">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                @if ($student)
                    <livewire:student::notification-bell />

                    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="true"
                            aria-label="Account"
                            class="flex size-9 items-center justify-center rounded-full bg-navy-50 font-display text-[13px] font-600 text-navy-700 transition-colors hover:bg-navy-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                        >
                            {{ mb_strtoupper(mb_substr($user->applicant->first_name, 0, 1).mb_substr($user->applicant->last_name, 0, 1)) }}
                        </button>
                        <div
                            x-cloak
                            x-show="open"
                            x-on:click.outside="open = false"
                            x-transition.opacity.duration.100ms
                            class="absolute right-0 top-11 z-30 w-60 overflow-hidden rounded-card border border-line bg-surface shadow-lg"
                        >
                            <div class="border-b border-line px-4 py-3">
                                <p class="truncate font-body text-[14px] font-600 text-ink">{{ $user->applicant->first_name }} {{ $user->applicant->last_name }}</p>
                                <p class="truncate font-mono text-[12px] text-slate">{{ $user->applicant->student_id }}</p>
                            </div>
                            <a href="{{ route('student.notifications') }}" wire:navigate class="flex items-center gap-2 px-4 py-2.5 font-body text-[13px] text-ink hover:bg-paper focus-visible:bg-paper focus-visible:outline-none">
                                <x-ui.icon name="bell" class="size-4 text-slate" /> Notifications
                            </a>
                            <a href="{{ route('student.settings') }}" wire:navigate class="flex items-center gap-2 px-4 py-2.5 font-body text-[13px] text-ink hover:bg-paper focus-visible:bg-paper focus-visible:outline-none">
                                <x-ui.icon name="settings" class="size-4 text-slate" /> Settings
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 font-body text-[13px] text-ink hover:bg-paper hover:text-crimson-600 focus-visible:bg-paper focus-visible:outline-none">
                                    <x-ui.icon name="log-out" class="size-4" /> Log out
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif ($user)
                    @if ($user->isStudent())
                        <a href="{{ route('student.profile.complete') }}" wire:navigate class="inline-flex h-9 items-center rounded-control px-3 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50">Set up profile</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="inline-flex h-9 items-center rounded-control px-3 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50">Dashboard</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" wire:navigate class="ml-1 inline-flex h-9 items-center rounded-control border border-line px-3 font-body text-[13px] font-500 text-ink transition-colors hover:border-navy-600 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Log in</a>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1040px] px-4 py-6 sm:py-8">
        {{ $slot }}
    </main>

    <x-ui.document-viewer />
    <x-ui.toast />
</body>
</html>
