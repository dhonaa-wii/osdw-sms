{{-- An anchor that reads as a button. Same sizing and variants as x-ui.button. --}}
@props(['href', 'variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-forest-600 text-white hover:bg-forest-700 focus-visible:ring-forest-600',
        'secondary' => 'border border-line bg-surface text-ink hover:bg-paper focus-visible:ring-forest-600',
        'ghost' => 'text-forest-700 hover:bg-forest-50 focus-visible:ring-forest-600',
        'danger' => 'bg-crimson-600 text-white hover:bg-crimson-700 focus-visible:ring-crimson-600',
    ];
    $classes = $variants[$variant] ?? $variants['primary'];
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-control px-4 py-2 font-body text-[14px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 $classes"]) }}
>
    {{ $slot }}
</a>
