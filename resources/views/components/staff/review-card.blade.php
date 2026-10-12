@props(['icon', 'title'])

{{-- Section card on a review page: an icon + title header, optional `meta` on the right, body in the slot. --}}
<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-card border border-line bg-surface']) }}>
    <div class="flex items-center gap-2.5 border-b border-line px-4 py-2.5">
        <span class="flex size-7 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-700">
            <x-ui.icon :name="$icon" class="size-4" />
        </span>
        <h2 class="min-w-0 flex-1 truncate font-display text-[15px] font-600 text-ink">{{ $title }}</h2>
        @isset($meta)
            <div class="shrink-0">{{ $meta }}</div>
        @endisset
    </div>
    {{ $slot }}
</section>
