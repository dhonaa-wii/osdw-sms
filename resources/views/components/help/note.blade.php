@props(['tone' => 'info', 'title' => null])

{{-- Callout: info (azure), tip (navy), warning (amber), rule (crimson), award (gold). --}}
@php
    [$border, $iconColor, $icon] = match ($tone) {
        'warning' => ['border-l-amber-600', 'text-amber-600', 'clock'],
        'rule' => ['border-l-crimson-600', 'text-crimson-600', 'alert-circle'],
        'award' => ['border-l-gold-600', 'text-gold-600', 'award'],
        'tip' => ['border-l-navy-600', 'text-navy-600', 'check-circle'],
        default => ['border-l-azure-600', 'text-azure-600', 'info'],
    };
@endphp

<div data-help-keep {{ $attributes->merge(['class' => "my-5 flex gap-3 rounded-card border border-line border-l-[3px] {$border} bg-surface px-4 py-3"]) }}>
    <x-ui.icon :name="$icon" class="mt-1 size-[18px] {{ $iconColor }}" />
    <div class="min-w-0 flex-1 text-[14px] leading-6 text-slate [&_p+p]:mt-2">
        @if ($title)
            <p class="font-600 text-ink">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
