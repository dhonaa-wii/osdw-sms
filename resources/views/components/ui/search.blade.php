{{--
    Search field with a leading icon, a clear button and a spinner while the
    component refreshes. The label is visually hidden; the placeholder says what to type.
--}}
@props(['name', 'label', 'placeholder' => null])

<div {{ $attributes->only('class')->merge(['class' => 'relative']) }} x-data>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
    <input
        type="search"
        id="{{ $name }}"
        autocomplete="off"
        placeholder="{{ $placeholder }}"
        {{ $attributes->except('class')->merge(['class' => 'block h-10 w-full rounded-control border border-line bg-surface pl-9 pr-9 font-body text-[14px] text-ink placeholder:text-mist transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25 [&::-webkit-search-cancel-button]:hidden']) }}
    >
    <span class="absolute right-2.5 top-1/2 -translate-y-1/2">
        <span wire:loading.flex wire:target="{{ $name }}" class="size-5 items-center justify-center">
            <x-ui.spinner class="size-4 text-mist" />
        </span>
        <button
            type="button"
            wire:loading.remove
            wire:target="{{ $name }}"
            x-show="$wire.{{ $name }}"
            x-cloak
            @click="$wire.set('{{ $name }}', ''); $el.closest('div').querySelector('input').focus()"
            class="flex size-5 items-center justify-center rounded-full text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
            aria-label="Clear search"
        >
            <x-ui.icon name="x" class="size-3.5" />
        </button>
    </span>
</div>
