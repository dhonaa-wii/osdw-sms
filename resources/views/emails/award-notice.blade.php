@php
    $applicant = $award->application->applicant;
    $program = $award->programOffering->scholarshipProgram;
@endphp
<x-mail::message>
# Congratulations, {{ $applicant->first_name }}

You've been awarded **{{ $program->name }}**.

<x-mail::panel>
@if ($award->amount)
Amount: &#8369;{{ number_format($award->amount, 2) }}
@endif
@if ($award->discount_rate)
Tuition discount: {{ $award->discount_rate }}%
@endif
</x-mail::panel>

Log in to your tracking dashboard with your Student ID and reference number to accept or decline this award.
Declining frees the slot for another applicant, so please respond as soon as you can.

<x-mail::button :url="route('track.lookup')">
Respond to this award
</x-mail::button>

Thanks,<br>
OSDW &mdash; Office of Student Development and Welfare
</x-mail::message>
