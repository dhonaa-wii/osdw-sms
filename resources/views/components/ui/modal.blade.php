@props(['show' => false, 'title' => null, 'size' => 'lg'])

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
        <div class="flex w-full {{ $maxWidth }} max-h-full flex-col rounded-card border border-line bg-surface shadow-lg" data-modal-panel>
            @if ($title)
                <h2 class="shrink-0 px-6 pt-6 font-display text-[20px] font-600 text-ink">{{ $title }}</h2>
            @endif

            <div class="min-h-0 overflow-y-auto px-6 {{ $title ? 'pt-4' : 'pt-6' }} pb-6">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
