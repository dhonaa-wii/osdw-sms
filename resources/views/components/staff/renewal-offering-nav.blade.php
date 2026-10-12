@props(['offering', 'current', 'newCount', 'renewalCount'])

{{--
    Switch between a renewal offering's new-applicant workspace and its
    Renewals page. Only shown when the renewal offering takes new applicants.
--}}
@php
    $links = [
        'new' => ['label' => 'New applications', 'icon' => 'form', 'href' => route('staff.applications.workspace', $offering), 'count' => $newCount],
        'renewals' => ['label' => 'Renewals', 'icon' => 'clipboard-check', 'href' => route('staff.renewals.index', $offering), 'count' => $renewalCount],
    ];
@endphp

<nav {{ $attributes->merge(['class' => 'inline-flex rounded-control bg-surface p-1 ring-1 ring-line']) }} aria-label="Applications in this offering">
    @foreach ($links as $key => $link)
        <a
            href="{{ $link['href'] }}"
            wire:navigate
            @if ($current === $key) aria-current="page" @endif
            @class([
                'flex items-center gap-2 rounded-[6px] px-3 py-1.5 font-body text-[13px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600',
                'bg-navy-600 text-white' => $current === $key,
                'text-slate hover:bg-paper hover:text-ink' => $current !== $key,
            ])
        >
            <x-ui.icon :name="$link['icon']" class="size-4" />
            {{ $link['label'] }}
            <span @class([
                'font-mono text-[11px]',
                'text-white/80' => $current === $key,
                'text-amber-600' => $current !== $key && $link['count'] > 0,
                'text-mist' => $current !== $key && $link['count'] === 0,
            ])>{{ $link['count'] }}</span>
        </a>
    @endforeach
</nav>
