@php
    $applicant = $application->applicant;
    $offering = $application->programOffering;
@endphp
<x-mail::message>
# Hi {{ $applicant->first_name }},

Your **{{ $offering->scholarshipProgram->name }}** scholarship can continue into {{ $offering->displayName() }}.

To renew it, hand in the renewal requirements listed on your renewal page — upload the ones asked online and bring any physical copies to the OSDW office. OSDW reviews them and lets you know the result.

**Submit them by {{ $offering->closes_at->format('F j, Y, g:i A') }}.**

<x-mail::button :url="route('student.applications.show', $application)">
See your renewal requirements
</x-mail::button>

Log in to your OSDW Scholarships account to open your renewal.

Thanks,<br>
OSDW &mdash; Office of Student Development and Welfare
</x-mail::message>
