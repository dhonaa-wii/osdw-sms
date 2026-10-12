@props(['title' => 'Nothing here yet', 'message' => null])

<div class="flex flex-col items-center justify-center rounded-card border border-dashed border-line px-6 py-12 text-center">
    <span class="flex size-11 items-center justify-center rounded-full bg-navy-50 text-navy-700">
        @isset($icon)
            {{ $icon }}
        @else
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9 4.5 4.5A1 1 0 0 1 5.45 4h13.1a1 1 0 0 1 .95.5L21 9" />
                <path d="M3 9h18v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9Z" />
                <path d="M9 13h6" />
            </svg>
        @endisset
    </span>
    <p class="mt-3 font-display text-[16px] font-500 text-ink">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 font-body text-[14px] text-slate">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
