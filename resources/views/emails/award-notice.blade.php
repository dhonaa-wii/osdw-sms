@php
    $applicant = $award->application->applicant;
    $program = $award->programOffering->scholarshipProgram;
    $isRenewal = $award->application->isRenewal();
@endphp
<x-mail::message>
# Congratulations, {{ $applicant->first_name }}

@if ($isRenewal)
Your **{{ $program->name }}** scholarship has been renewed for {{ $award->programOffering->displayName() }}.
@else
You've been awarded **{{ $program->name }}**.
@endif

<x-mail::panel>
Amount: ₱{{ number_format($award->amount, 2) }}
</x-mail::panel>

Log in to your OSDW Scholarships account to view your award record.

<x-mail::button :url="route('student.applications.show', $award->application)">
View your award
</x-mail::button>

Thanks,<br>
OSDW &mdash; Office of Student Development and Welfare
</x-mail::message>
