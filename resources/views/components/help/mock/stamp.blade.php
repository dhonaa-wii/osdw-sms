@props(['tone' => 'navy'])

{{-- A status stamp at screenshot scale, in the same colours as ApplicationStatus::stampClasses(). --}}
@php
    $classes = match ($tone) {
        'amber' => 'border-amber-600 text-amber-600',
        'crimson' => 'border-crimson-600 text-crimson-600',
        'gold' => 'border-gold-600 text-gold-600',
        'azure' => 'border-azure-600 text-azure-600',
        'mist' => 'border-mist text-mist',
        default => 'border-navy-600 text-navy-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-block whitespace-nowrap border-l-2 pl-1.5 font-body text-[9px] font-600 uppercase leading-tight tracking-[0.08em] {$classes}"]) }}>{{ $slot }}</span>
