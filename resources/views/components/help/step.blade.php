@props(['n'])

{{-- A numbered step; the number matches a marker in the screenshot above it when there is one. --}}
<li {{ $attributes->merge(['class' => 'flex gap-3']) }}>
    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-navy-600 font-body text-[12px] font-600 text-white">{{ $n }}</span>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</li>
