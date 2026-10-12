@props(['status' => null, 'application' => null])

@php
    // An application stamp reads through the model so renewals get their own
    // stage names; anything else stamps its status enum directly.
    $label = $application ? $application->statusLabel() : $status->label();
    $classes = $application ? $application->statusStampClasses() : $status->stampClasses();
@endphp

<span {{ $attributes->merge(['class' => "border-l-2 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] {$classes}"]) }}>{{ $label }}</span>
