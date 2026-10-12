{{--
    Square icon-only row action. Pass `href` for a link (opened with wire:navigate),
    otherwise it renders a button. `label` is the accessible name and the tooltip.
    tone: 'default' | 'danger' | 'primary'. Pass `loading` with the wire:target to
    swap the icon for a spinner while the action runs.
--}}
@props(['icon', 'label', 'href' => null, 'tone' => 'default', 'loading' => null])

@php
    $tones = [
        'default' => 'text-slate hover:bg-navy-50 hover:text-navy-700',
        'primary' => 'text-navy-700 hover:bg-navy-50',
        'danger' => 'text-slate hover:bg-crimson-600/10 hover:text-crimson-600',
    ];
    $classes = 'flex size-8 shrink-0 items-center justify-center rounded-control transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:cursor-not-allowed disabled:opacity-50 '.($tones[$tone] ?? $tones['default']);
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="size-4" />
    </a>
@else
    <button
        type="button"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        @if ($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if ($loading)
            <x-ui.icon :name="$icon" class="size-4" wire:loading.remove wire:target="{{ $loading }}" />
            <x-ui.spinner class="size-4" wire:loading wire:target="{{ $loading }}" />
        @else
            <x-ui.icon :name="$icon" class="size-4" />
        @endif
    </button>
@endif
