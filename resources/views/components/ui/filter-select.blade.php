{{--
    Toolbar filter: a select with a leading icon and a visually hidden label,
    matching <x-ui.search>. Options go in the slot.
--}}
@props(['name', 'label', 'icon' => 'sliders'])

<div {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <x-ui.icon :name="$icon" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
    <select
        id="{{ $name }}"
        {{ $attributes->except('class')->merge(['class' => 'block h-10 w-full appearance-none truncate rounded-control border border-line bg-surface pl-9 pr-9 font-body text-[14px] text-ink transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25']) }}
    >
        {{ $slot }}
    </select>
    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
</div>
