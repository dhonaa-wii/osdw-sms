@props(['label' => null, 'name', 'placeholder' => null, 'required' => false, 'hint' => null, 'compact' => false])

<div>
    @if ($label)
        <label for="{{ $name }}" class="block font-body text-[13px] font-500 text-ink">
            {{ $label }}
            @if ($required)
                <span class="text-crimson-600">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $name }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-control border border-line bg-surface text-ink focus-visible:outline-none transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:ring-2 focus-visible:ring-navy-600/25 placeholder:text-mist '.($compact ? 'px-2.5 py-1 text-[13px]' : 'px-3 py-1.5 text-[16px] sm:text-[14px]')]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @if ($hint && ! $errors->has($name))
        <p class="mt-1 font-body {{ $compact ? 'text-[11px] sm:text-[12px]' : 'text-[13px]' }} text-mist">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 font-body {{ $compact ? 'text-[11px] sm:text-[12px]' : 'text-[13px]' }} text-crimson-600">{{ $message }}</p>
    @enderror
</div>
