@props(['variant' => 'primary', 'type' => 'button', 'loading' => null])

{{--
    Pass `loading` with the wire:target to swap the label for a spinner in place:
    the label only turns invisible, so the button keeps its width and nothing shifts.
--}}

@php
    $variants = [
        'primary' => 'bg-navy-600 text-white hover:bg-navy-700 focus-visible:ring-navy-600',
        'secondary' => 'border border-line bg-surface text-ink hover:bg-paper focus-visible:ring-navy-600',
        'ghost' => 'text-navy-700 hover:bg-navy-50 focus-visible:ring-navy-600',
        'danger' => 'bg-crimson-600 text-white hover:bg-crimson-700 focus-visible:ring-crimson-600',
    ];
    $classes = $variants[$variant] ?? $variants['primary'];
@endphp

<button
    type="{{ $type }}"
    @if ($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif
    {{ $attributes->merge(['class' => "relative inline-flex items-center justify-center gap-2 rounded-control px-4 py-2 font-body text-[14px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 $classes"]) }}
>
    @if ($loading)
        <span class="inline-flex items-center gap-2" wire:loading.class="invisible" wire:target="{{ $loading }}">{{ $slot }}</span>
        <span class="absolute inset-0 hidden items-center justify-center" wire:loading.class.remove="hidden" wire:loading.class="flex" wire:target="{{ $loading }}">
            <x-ui.spinner />
        </span>
    @else
        {{ $slot }}
    @endif
</button>
