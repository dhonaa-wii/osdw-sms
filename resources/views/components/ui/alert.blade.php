@props(['type' => 'info'])

@php
    $variants = [
        'info' => ['border-azure-600', 'text-azure-600'],
        'success' => ['border-forest-600', 'text-forest-700'],
        'warning' => ['border-amber-600', 'text-amber-600'],
        'danger' => ['border-crimson-600', 'text-crimson-600'],
    ];
    [$border, $text] = $variants[$type] ?? $variants['info'];
@endphp

<div {{ $attributes->merge(['class' => "border-l-2 $border bg-surface px-4 py-3"]) }}>
    <p class="font-body text-[14px] {{ $text }}">{{ $slot }}</p>
</div>
