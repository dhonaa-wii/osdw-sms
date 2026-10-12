{{--
    Server-driven modal. Pass `close` with the wire expression that hides it
    (e.g. "$set('showModal', false)") to get a close button in the header.
--}}
@props(['show' => false, 'title' => null, 'size' => 'lg', 'close' => null])

@php
    $maxWidth = match ($size) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        default => 'max-w-lg',
    };
@endphp

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4 py-8" data-modal-backdrop>
        <div class="flex w-full {{ $maxWidth }} max-h-full flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10" data-modal-panel role="dialog" aria-modal="true">
            @if ($title)
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-line py-2.5 pl-4 pr-2.5">
                    <h2 class="min-w-0 truncate font-display text-[15px] font-600 text-ink">{{ $title }}</h2>
                    @if ($close)
                        <button type="button" wire:click="{{ $close }}" class="flex size-7 shrink-0 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Close">
                            <x-ui.icon name="x" class="size-4" />
                        </button>
                    @endif
                </div>
            @endif

            <div class="min-h-0 overflow-y-auto px-4 py-3.5">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
