@php
    $applicant = $application->applicant;
    $program = $application->programOffering->scholarshipProgram;
    $rejected = $application->documents->where('status', 'rejected');
@endphp
<x-mail::message>
# Hi {{ $applicant->first_name }}

Your application for **{{ $program->name }}** (reference **{{ $application->reference_no }}**) needs a document fixed before it can move forward.

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

Log in to your tracking dashboard with your Student ID and reference number to re-upload the document.

<x-mail::button :url="route('track.lookup')">
Fix my application
</x-mail::button>

Thanks,<br>
OSDW — Office of Student Development and Welfare
</x-mail::message>
