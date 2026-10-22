@props(['url', 'caption', 'variant' => 'public', 'active' => null, 'admin' => false, 'student' => true])

{{--
    A coded "screenshot": a browser frame around a small mock of a real page, built from the same tokens.
    `variant`: public (student header), staff (sidebar and top bar), bare (content only).
    The mock is inert, so nothing in it can be focused or clicked.
--}}
@php
    $staffNav = array_values(array_filter([
        'Dashboard',
        $admin ? 'Scholarship Programs' : null,
        $admin ? 'Providers' : null,
        'Applications', 'Grantees', 'Announcements', 'Reports',
    ]));
    $adminNav = ['Audit Log', 'Users', 'Settings'];
    $navItem = fn (string $item) => 'rounded-[5px] px-1.5 py-1 font-body text-[10px] font-500 '
        .($item === $active ? 'border-l-2 border-navy-600 bg-navy-50 text-navy-700' : 'text-slate');
    $publicLink = fn (string $item) => 'hidden items-center gap-1 rounded-[5px] px-1.5 py-0.5 font-body text-[10px] font-500 sm:inline-flex '
        .($item === $active ? 'bg-navy-50 text-navy-700' : 'text-slate');
@endphp

<figure data-help-screen class="my-6">
    <div class="overflow-hidden rounded-card border border-line bg-surface shadow-sm">
        <div class="flex h-8 items-center gap-3 border-b border-line bg-paper px-3" aria-hidden="true">
            <span class="flex gap-1.5">
                <span class="size-2 rounded-full bg-line"></span>
                <span class="size-2 rounded-full bg-line"></span>
                <span class="size-2 rounded-full bg-line"></span>
            </span>
            <span class="flex h-5 min-w-0 max-w-[340px] flex-1 items-center gap-1.5 rounded-full border border-line bg-surface px-2.5">
                <x-ui.icon name="lock" class="size-3 text-mist" />
                <span class="truncate font-mono text-[10px] text-mist">osdw-sms{{ $url }}</span>
            </span>
        </div>

        <div role="img" aria-label="{{ $caption }}" inert class="pointer-events-none select-none text-left font-body text-[11px] leading-[1.45] text-ink">
            @if ($variant === 'staff')
                <div class="flex min-h-[220px]">
                    <div class="hidden w-[132px] shrink-0 flex-col border-r border-line bg-surface sm:flex">
                        <div class="flex flex-col items-center gap-1 px-3 py-3">
                            <span class="flex size-7 items-center justify-center rounded-full bg-navy-600 text-white"><x-ui.icon name="graduation" class="size-3.5" /></span>
                            <span class="font-display text-[11px] font-600 text-navy-700">OSDW SMS</span>
                        </div>
                        <div class="space-y-0.5 px-2">
                            @foreach ($staffNav as $item)
                                <div class="{{ $navItem($item) }}">{{ $item }}</div>
                            @endforeach
                        </div>
                        @if ($admin)
                            <div class="mt-2 space-y-0.5 border-t border-line px-2 pt-2">
                                @foreach ($adminNav as $item)
                                    <div class="{{ $navItem($item) }}">{{ $item }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 bg-paper">
                        <div class="flex h-8 items-center justify-end gap-2 border-b border-line bg-surface px-3">
                            <span class="mr-auto font-display text-[11px] font-600 text-navy-700 sm:hidden">OSDW SMS</span>
                            <x-ui.icon name="help" class="size-3.5 text-slate" />
                            <x-ui.icon name="bell" class="size-3.5 text-slate" />
                            <span class="flex size-5 items-center justify-center rounded-full bg-navy-50 font-display text-[8px] font-600 text-navy-700">{{ $admin ? 'AD' : 'MS' }}</span>
                        </div>
                        <div class="p-3 sm:p-4">{{ $slot }}</div>
                    </div>
                </div>
            @elseif ($variant === 'public')
                <div class="flex h-9 items-center gap-2 border-b border-line bg-surface px-3">
                    <span class="flex size-5 items-center justify-center rounded-full bg-navy-600 text-white"><x-ui.icon name="graduation" class="size-3" /></span>
                    <span class="font-display text-[11px] font-600 text-navy-700">OSDW Scholarships</span>
                    <span class="ml-auto flex items-center gap-1">
                        <span class="{{ $publicLink('Browse') }}"><x-ui.icon name="search" class="size-3" /> Browse</span>
                        @if ($student)
                            <span class="{{ $publicLink('My applications') }}"><x-ui.icon name="form" class="size-3" /> My applications</span>
                        @endif
                        <span class="{{ $publicLink('Results') }}"><x-ui.icon name="award" class="size-3" /> Results</span>
                        <x-ui.icon name="help" class="mx-0.5 size-3.5 text-slate" />
                        @if ($student)
                            <x-ui.icon name="bell" class="mx-0.5 size-3.5 text-slate" />
                            <span class="flex size-5 items-center justify-center rounded-full bg-navy-50 font-display text-[8px] font-600 text-navy-700">JD</span>
                        @else
                            <span class="rounded-[5px] border border-line px-1.5 py-0.5 text-[10px] font-500 text-ink">Log in</span>
                        @endif
                    </span>
                </div>
                <div class="bg-paper p-3 sm:p-5">{{ $slot }}</div>
            @else
                <div class="bg-paper p-3 sm:p-5">{{ $slot }}</div>
            @endif
        </div>
    </div>
    <figcaption class="mt-2 font-body text-[13px] leading-5 text-mist">
        <span data-help-figure-no class="mr-1 font-500 text-slate"></span>{{ $caption }}
    </figcaption>
</figure>
