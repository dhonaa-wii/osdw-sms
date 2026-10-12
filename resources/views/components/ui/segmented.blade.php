{{--
    Segmented switch with a count per option, as on the admin list pages.

    Server-driven: pass `model` (the Livewire property) and the current `value`;
    each option sets it with a round trip.
    Client-driven: pass `alpine` (an Alpine expression, e.g. "tab") instead; the
    switch flips that value with no round trip.

    `options` maps value => label; `counts` maps value => number (optional);
    `alert` lists values whose non-zero count should read as pending (amber).
--}}
@props(['options', 'counts' => [], 'model' => null, 'value' => null, 'alpine' => null, 'alert' => [], 'label' => 'Filter'])

<div {{ $attributes->merge(['class' => 'flex rounded-control bg-paper p-1']) }} role="group" aria-label="{{ $label }}">
    @foreach ($options as $optionValue => $optionLabel)
        @php
            $count = $counts[$optionValue] ?? null;
            $pending = in_array($optionValue, $alert, true) && $count > 0;
            $base = 'flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-[6px] px-3 py-1.5 font-body text-[13px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600';
        @endphp
        @if ($alpine)
            <button
                type="button"
                wire:key="segment-{{ $optionValue }}"
                x-on:click="{{ $alpine }} = @js($optionValue)"
                x-bind:aria-pressed="({{ $alpine }} === @js($optionValue)).toString()"
                x-bind:class="{{ $alpine }} === @js($optionValue) ? 'bg-surface text-ink shadow-sm' : 'text-slate hover:text-ink'"
                class="{{ $base }}"
            >
                {{ $optionLabel }}
                @if ($count !== null)
                    <span class="font-mono text-[11px] {{ $pending ? 'text-amber-600' : 'text-mist' }}">{{ $count }}</span>
                @endif
            </button>
        @else
            <button
                type="button"
                wire:key="segment-{{ $optionValue }}"
                wire:click="$set('{{ $model }}', '{{ $optionValue }}')"
                aria-pressed="{{ $value === $optionValue ? 'true' : 'false' }}"
                @class([
                    $base,
                    'bg-surface text-ink shadow-sm' => $value === $optionValue,
                    'text-slate hover:text-ink' => $value !== $optionValue,
                ])
            >
                {{ $optionLabel }}
                @if ($count !== null)
                    <span class="font-mono text-[11px] {{ $pending ? 'text-amber-600' : 'text-mist' }}">{{ $count }}</span>
                @endif
            </button>
        @endif
    @endforeach
</div>
