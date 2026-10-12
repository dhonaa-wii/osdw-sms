@php
    $applicant = $application->applicant;
    $program = $application->programOffering->scholarshipProgram;
    $rejected = $application->documents->where('status', 'rejected');
@endphp
<x-mail::message>
# Hi {{ $applicant->first_name }}

Your application for **{{ $program->name }}** needs a document fixed before it can move forward.

@if ($rejected->isNotEmpty())
<x-mail::panel>
@foreach ($rejected as $document)
**{{ $document->offeringFormField->label }}**
@if ($document->remarks)
{{ $document->remarks }}
@endif

@endforeach
</x-mail::panel>
@endif

Log in to your OSDW Scholarships account to see what needs fixing.

<x-mail::button :url="route('student.applications.show', $application)">
Fix my application
</x-mail::button>

Thanks,<br>
OSDW — Office of Student Development and Welfare
</x-mail::message>
