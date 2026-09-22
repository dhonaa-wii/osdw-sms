@php
    $applicant = $application->applicant;
    $program = $application->programOffering->scholarshipProgram;
@endphp
<x-mail::message>
# Application received

Hi {{ $applicant->first_name }},

We received your application to **{{ $program->name }}**. Keep this reference number &mdash; you'll need it along with your Student ID to check your application status.

<x-mail::panel>
{{ $application->reference_no }}
</x-mail::panel>

<x-mail::button :url="route('track.lookup')">
Track your application
</x-mail::button>

Thanks,<br>
OSDW &mdash; Office of Student Development and Welfare
</x-mail::message>
