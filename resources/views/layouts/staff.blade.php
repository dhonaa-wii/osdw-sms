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
<body class="min-h-screen bg-paper font-body text-ink antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
        ></div>

        <x-app.sidebar
            class="w-[260px] shrink-0"
            x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            @click="sidebarOpen = false"
        />

        <div class="flex-1 min-w-0">
            @php
                $user = auth()->user();
                $initials = mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join(''));
            @endphp

            {{-- Top bar: the menu button and name below lg, the bell and account menu at every width. --}}
            <div class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-surface/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="flex size-9 shrink-0 items-center justify-center rounded-control text-ink hover:bg-paper lg:hidden"
                    aria-label="Open navigation"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <span class="font-display text-[15px] font-600 text-navy-700 lg:hidden">OSDW SMS</span>

                <div class="ml-auto flex items-center gap-1">
                    <livewire:staff::notification-bell />

                    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="true"
                            aria-label="Account"
                            class="flex size-9 items-center justify-center rounded-full bg-navy-50 font-display text-[13px] font-600 text-navy-700 transition-colors hover:bg-navy-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                        >
                            {{ $initials }}
                        </button>
                        <div
                            x-cloak
                            x-show="open"
                            x-on:click.outside="open = false"
                            x-transition.opacity.duration.100ms
                            class="absolute right-0 top-11 z-30 w-60 overflow-hidden rounded-card border border-line bg-surface shadow-lg"
                        >
                            <div class="border-b border-line px-4 py-3">
                                <p class="truncate font-body text-[14px] font-600 text-ink">{{ $user->name }}</p>
                                <p class="truncate font-body text-[12px] text-slate">{{ $user->role->label() }}</p>
                            </div>
                            <a href="{{ route('staff.notifications') }}" class="flex items-center gap-2 px-4 py-2.5 font-body text-[13px] text-ink hover:bg-paper focus-visible:bg-paper focus-visible:outline-none">
                                <x-ui.icon name="bell" class="size-4 text-slate" /> Notifications
                            </a>
                            <a href="{{ route('staff.profile') }}" class="flex items-center gap-2 px-4 py-2.5 font-body text-[13px] text-ink hover:bg-paper focus-visible:bg-paper focus-visible:outline-none">
                                <x-ui.icon name="user" class="size-4 text-slate" /> Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 font-body text-[13px] text-ink hover:bg-paper hover:text-crimson-600 focus-visible:bg-paper focus-visible:outline-none">
                                    <x-ui.icon name="log-out" class="size-4" /> Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.document-viewer />
    <x-ui.notice-modal />
    <x-ui.confirm-modal />
</body>
</html>
