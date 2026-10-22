@props(['term'])

<div data-help-keep class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-[190px_1fr] sm:gap-4">
    <p class="font-body text-[14px] font-600 leading-6 text-ink">{{ $term }}</p>
    <div class="font-body text-[14px] leading-6 text-slate">{{ $slot }}</div>
</div>
