@props(['class' => ''])

@php
    $isAdmin = auth()->user()?->role === \App\Enums\UserRole::Admin;

    $link = fn (bool $active) => 'block rounded-control px-2 py-1.5 text-[14px] font-500 transition-colors short:py-1 short:text-[13px] shorter:py-0.5'
        .($active
            ? ' border-l-2 border-forest-600 bg-forest-50 text-forest-700'
            : ' text-slate hover:bg-paper hover:text-ink');

    $navItems = collect([
        ['label' => 'Dashboard', 'route' => 'dashboard', 'match' => 'dashboard'],
        ['label' => 'Scholarship Programs', 'route' => 'admin.scholarship-programs', 'match' => 'admin.scholarship-programs', 'adminOnly' => true],
        ['label' => 'Providers', 'route' => 'admin.providers', 'match' => 'admin.providers', 'adminOnly' => true],
        ['label' => 'Applicants', 'route' => 'staff.applications.index', 'match' => 'staff.applications.*'],
        ['label' => 'Award Processing', 'route' => 'staff.awards.index', 'match' => 'staff.awards.*'],
        ['label' => 'Scholar Monitoring', 'route' => 'staff.scholars.index', 'match' => 'staff.scholars.*'],
        ['label' => 'Announcements', 'route' => 'staff.announcements.index', 'match' => 'staff.announcements.*'],
        ['label' => 'Reports', 'route' => 'staff.reports.index', 'match' => 'staff.reports.*'],
    ])->reject(fn ($item) => ($item['adminOnly'] ?? false) && ! $isAdmin);

    $adminItems = [
        ['label' => 'Audit Log', 'route' => 'audit-log.index', 'match' => 'audit-log.*'],
        ['label' => 'Users', 'route' => 'admin.users', 'match' => 'admin.users'],
        ['label' => 'Settings', 'route' => 'admin.settings', 'match' => ['admin.settings', 'admin.campus', 'admin.colleges', 'admin.programs', 'admin.academic-terms']],
    ];
@endphp

{{-- The sidebar is capped at the viewport height and never scrolls; spacing tightens on short screens instead. --}}
<aside {{ $attributes->merge(['class' => 'sticky top-0 flex h-screen flex-col overflow-hidden border-r border-line bg-surface']) }}>
    <div class="px-6 py-6 short:py-4 shorter:py-3">
        <span class="font-display text-[16px] font-600 text-forest-700">OSDW SMS</span>
    </div>

    <nav class="min-h-0 flex-1 overflow-hidden px-4 pb-6 short:pb-3">
        <ul class="space-y-1 short:space-y-0.5">
            @foreach ($navItems as $item)
                <li>
                    <a href="{{ route($item['route']) }}" class="{{ $link(request()->routeIs($item['match'])) }}">
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($isAdmin)
        <div class="border-t border-line px-4 py-3 short:py-2">
            <ul class="space-y-1 short:space-y-0.5">
                @foreach ($adminItems as $item)
                    <li>
                        <a href="{{ route($item['route']) }}" class="{{ $link(request()->routeIs($item['match'])) }}">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="border-t border-line px-4 py-3 short:py-2">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-[14px] font-500 text-slate transition-colors hover:bg-paper hover:text-crimson-600 short:py-1 short:text-[13px]">
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <path d="M16 17l5-5-5-5" />
                    <path d="M21 12H9" />
                </svg>
                Log out
            </button>
        </form>
    </div>
</aside>
