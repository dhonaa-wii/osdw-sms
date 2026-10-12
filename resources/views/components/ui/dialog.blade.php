{{--
    Client-driven modal. `show` and `close` are Alpine expressions, usually a
    Livewire property read and written through $wire:

        <x-ui.dialog show="$wire.showForm" close="$wire.showForm = false" title="Edit offering" loading="create, edit">

    Because visibility lives in the browser, the dialog opens on click with no
    round trip, and a Livewire re-render never closes or re-animates it. Pass
    `loading` with the wire targets that fill the form so a spinner covers the
    body while they run.
--}}
@props(['show', 'close', 'title' => null, 'size' => 'md', 'loading' => null, 'layer' => 'z-50'])

@php
    $maxWidth = match ($size) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        default => 'max-w-md',
    };
@endphp

<div
    x-show="{{ $show }}"
    x-cloak
    x-transition:leave="transition duration-100 ease-in"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 {{ $layer }} flex items-center justify-center bg-ink/40 px-4 py-8"
    data-modal-backdrop
    x-on:click.self="{{ $close }}"
    x-on:keydown.escape.window="if ({{ $show }}) { {{ $close }} }"
>
    <div
        x-show="{{ $show }}"
        x-trap.noscroll="{{ $show }}"
        {{ $attributes->merge(['class' => "flex max-h-full w-full $maxWidth flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10"]) }}
        data-modal-panel
        role="dialog"
        aria-modal="true"
    >
        @if ($title)
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-line py-2.5 pl-4 pr-2.5">
                <h2 class="min-w-0 truncate font-display text-[15px] font-600 text-ink">{{ $title }}</h2>
                <button type="button" x-on:click="{{ $close }}" class="flex size-7 shrink-0 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Close">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>
        @endif

        <div class="relative min-h-0 overflow-y-auto px-4 py-3.5">
            {{ $slot }}

            @if ($loading)
                <div wire:loading.flex wire:target="{{ $loading }}" class="absolute inset-0 hidden items-center justify-center bg-surface/80">
                    <x-ui.spinner class="size-5 text-navy-600" />
                </div>
            @endif
        </div>
    </div>
</div>
