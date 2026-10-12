@props(['label'])

<div class="min-w-0">
    <dt class="font-body text-[12px] text-mist">{{ $label }}</dt>
    <dd {{ $attributes->merge(['class' => 'mt-0.5 break-words font-body text-[14px] text-ink']) }}>{{ $slot }}</dd>
</div>
