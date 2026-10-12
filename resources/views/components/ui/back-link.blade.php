@props(['href', 'label' => 'Back'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-1.5 font-body text-[13px] font-500 text-slate transition-colors hover:text-navy-700']) }}>
    <svg class="size-4 shrink-0 transition-transform duration-150 ease-out group-hover:-translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M19 12H5" />
        <path d="m11 6-6 6 6 6" />
    </svg>
    {{ $label }}
</a>
