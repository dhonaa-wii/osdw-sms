{{-- A student's identity as filed on their profile, read-only. --}}
@php
    $termClass = 'font-body text-[12px] text-mist';
    $valueClass = 'font-body text-[14px] text-ink';
@endphp
<dl class="grid grid-cols-1 gap-x-5 gap-y-3 p-5 sm:grid-cols-2">
    <div><dt class="{{ $termClass }}">Student ID</dt><dd class="font-mono text-[14px] text-ink">{{ $applicant->student_id }}</dd></div>
    <div><dt class="{{ $termClass }}">Sex</dt><dd class="{{ $valueClass }}">{{ $applicant->sex->label() }}</dd></div>
    <div><dt class="{{ $termClass }}">Name</dt><dd class="{{ $valueClass }}">{{ $applicant->fullName() }}</dd></div>
    <div><dt class="{{ $termClass }}">Birthdate</dt><dd class="{{ $valueClass }}">{{ $applicant->birthdate?->format('M j, Y') ?? '—' }}</dd></div>
    <div><dt class="{{ $termClass }}">Email</dt><dd class="{{ $valueClass }} break-all">{{ $applicant->email }}</dd></div>
    <div><dt class="{{ $termClass }}">Mobile number</dt><dd class="{{ $valueClass }}">{{ $applicant->mobile ?: '—' }}</dd></div>
    <div class="sm:col-span-2"><dt class="{{ $termClass }}">Address</dt><dd class="{{ $valueClass }}">{{ $applicant->address ?: '—' }}</dd></div>
</dl>
