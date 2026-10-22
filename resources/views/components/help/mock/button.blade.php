@props(['variant' => 'primary', 'icon' => null])

{{-- A button at screenshot scale, in the same variants as <x-ui.button>. --}}
@php
    $classes = match ($variant) {
        'secondary' => 'border border-line bg-surface text-ink',
        'ghost' => 'text-navy-700',
        'danger' => 'bg-crimson-600 text-white',
        'disabled' => 'bg-navy-600 text-white opacity-50',
        default => 'bg-navy-600 text-white',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-[6px] px-2.5 py-1 font-body text-[10.5px] font-500 {$classes}"]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="size-3" />
    @endif
    {{ $slot }}
</span>
