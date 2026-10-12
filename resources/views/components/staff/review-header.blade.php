@props(['application', 'applicant'])

{{-- Name, status and identifying facts at the top of a review page. --}}
@php
    $offering = $application->programOffering;
    $initials = mb_strtoupper(mb_substr($applicant->first_name, 0, 1).mb_substr($applicant->last_name, 0, 1));
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-3 rounded-card border border-line bg-surface px-4 py-3.5']) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-navy-50 font-display text-[15px] font-600 text-navy-700" aria-hidden="true">{{ $initials }}</span>

    <div class="min-w-0 flex-1">
        <h1 class="truncate font-display text-[22px] font-600 leading-tight text-ink">{{ $applicant->last_name }}, {{ $applicant->first_name }}</h1>
        <dl class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 font-body text-[13px] text-slate">
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">Student ID</dt>
                <x-ui.icon name="hash" class="size-3.5 text-mist" />
                <dd class="font-mono text-[12px] text-ink">{{ $applicant->student_id }}</dd>
            </div>
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">College</dt>
                <x-ui.icon name="building" class="size-3.5 text-mist" />
                <dd title="{{ $application->college->name }}">{{ $application->college->code }}</dd>
            </div>
            <div class="flex min-w-0 items-center gap-1.5">
                <dt class="sr-only">Scholarship</dt>
                <x-ui.icon name="award" class="size-3.5 text-mist" />
                <dd class="truncate">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</dd>
            </div>
        </dl>
    </div>

    <x-ui.status-stamp :application="$application" class="shrink-0" />
</div>
