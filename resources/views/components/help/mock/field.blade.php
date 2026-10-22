@props(['label', 'value' => '', 'mono' => false, 'placeholder' => false, 'select' => false, 'readonly' => false])

{{-- A labelled input at screenshot scale. --}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <p class="mb-0.5 font-body text-[10px] font-500 text-slate">{{ $label }}</p>
    <div @class([
        'flex h-7 items-center justify-between gap-2 rounded-[6px] border border-line px-2 text-[11px]',
        'bg-paper' => $readonly,
        'bg-surface' => ! $readonly,
    ])>
        <span @class(['truncate', 'font-mono' => $mono, 'text-mist' => $placeholder, 'text-ink' => ! $placeholder])>{{ $value }}{{ $slot }}</span>
        @if ($select)
            <x-ui.icon name="chevron-down" class="size-3 text-mist" />
        @endif
        @if ($readonly)
            <x-ui.icon name="lock" class="size-3 text-mist" />
        @endif
    </div>
</div>
