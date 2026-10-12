{{-- Empty state inside a list card (no border of its own). Put the next action in the slot. --}}
@props(['icon' => 'form', 'title', 'message' => null])

<div {{ $attributes->merge(['class' => 'px-6 py-12 text-center']) }}>
    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-3 font-display text-[16px] font-600 text-ink">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 font-body text-[14px] text-slate">{{ $message }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4 flex justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
