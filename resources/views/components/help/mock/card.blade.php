@props(['title' => null, 'icon' => null])

{{-- A section card at screenshot scale, shaped like <x-staff.review-card>. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[10px] border border-line bg-surface']) }}>
    @if ($title)
        <div class="flex items-center gap-2 border-b border-line px-3 py-2">
            @if ($icon)
                <span class="flex size-5 shrink-0 items-center justify-center rounded-[5px] bg-navy-50 text-navy-700">
                    <x-ui.icon :name="$icon" class="size-3" />
                </span>
            @endif
            <span class="min-w-0 flex-1 truncate font-display text-[11.5px] font-600 text-ink">{{ $title }}</span>
            @isset($meta)
                <span class="shrink-0">{{ $meta }}</span>
            @endisset
        </div>
    @endif
    {{ $slot }}
</div>
