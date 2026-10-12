@props(['active', 'activeLabel' => 'Active', 'inactiveLabel' => 'Inactive'])

@if ($active)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 border-l-2 border-navy-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-navy-700']) }}>{{ $activeLabel }}</span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 border-l-2 border-mist pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist']) }}>{{ $inactiveLabel }}</span>
@endif
