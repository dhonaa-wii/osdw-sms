@props(['n'])

{{-- Numbered callout placed on a mock screenshot; the steps below refer to it. --}}
<span {{ $attributes->merge(['class' => 'inline-flex size-[18px] shrink-0 items-center justify-center rounded-full bg-navy-600 font-body text-[10px] font-600 leading-none text-white ring-2 ring-surface']) }}>{{ $n }}</span>
