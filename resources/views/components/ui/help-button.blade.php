@props(['navigate' => false, 'variant' => 'icon'])

{{--
    Top-bar link to the Help Center, opening on the chapter for the signed-in role.
    `icon`: an icon button whose "Help" label shows from lg up (app top bars).
    `text`: a plain nav link (landing page header).
--}}
@php
    $user = auth()->user();
    $chapter = match (true) {
        $user === null, $user->isStudent() => 'students',
        $user->role === \App\Enums\UserRole::Admin => 'administrators',
        default => 'staff',
    };
    $classes = $variant === 'text'
        ? 'inline-flex items-center gap-1.5 rounded-control text-ink hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600'
        : 'inline-flex h-9 items-center gap-1.5 rounded-control px-2.5 font-body text-[13px] font-500 text-slate transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600';
@endphp

<a
    href="{{ route('help') }}#{{ $chapter }}"
    @if ($navigate) wire:navigate @endif
    title="Help Center"
    {{ $attributes->merge(['class' => $classes]) }}
>
    <x-ui.icon name="help" class="size-[18px]" />
    @if ($variant === 'text')
        Help Center
    @else
        <span class="hidden lg:inline">Help</span>
        <span class="sr-only lg:hidden">Help Center</span>
    @endif
</a>
