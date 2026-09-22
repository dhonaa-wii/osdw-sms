@props(['status'])

<span {{ $attributes->merge(['class' => "border-l-2 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] {$status->stampClasses()}"]) }}>{{ $status->label() }}</span>
