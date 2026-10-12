<?php

use App\Enums\EligibilityField;
use App\Enums\OfferingStatus;
use App\Models\ProgramOffering;
use App\Services\ScholarshipHoldingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Scholarship Details'))] class extends Component
{
    public ProgramOffering $offering;

    public function mount(ProgramOffering $offering): void
    {
        // The public site shows only offerings open for registration. Students
        // follow closed ones they applied to from My applications.
        abort_unless(
            $offering->takesNewApplicants()
                && $offering->registrationStatus() === OfferingStatus::Open
                && $offering->scholarshipProgram->is_active,
            404
        );

        $this->offering = $offering->load([
            'scholarshipProgram.provider',
            'academicTerm',
            'renewsOffering',
            // The family income cap is screened but never shown to students.
            'eligibilityRules' => fn ($query) => $query->where('field', '!=', EligibilityField::Income->value),
            'offeringColleges.college',
            'formFields' => fn ($query) => $query->orderBy('sort_order'),
            'announcements' => fn ($query) => $query->where('is_published', true)->orderByDesc('published_at'),
        ]);
    }

    public function isCollegeRestricted(): bool
    {
        return $this->offering->college_scope->value === 'restricted' || $this->offering->allocation_mode->value === 'quota';
    }

    public function with(): array
    {
        $applicant = auth()->user()?->applicant;

        return [
            // The signed-in student's own application here, if they already applied.
            'myApplication' => $applicant?->applications()->where('program_offering_id', $this->offering->id)->first(),
            // Why the signed-in student can't apply here (a national grantee applying to another national scholarship).
            'applyBlocker' => $applicant ? app(ScholarshipHoldingService::class)->applyBlocker($applicant, $this->offering) : null,
        ];
    }
};
?>

@php
    $status = $offering->registrationStatus();
    $isOpen = $status === OfferingStatus::Open;
    $closingSoon = $isOpen && $offering->closes_at <= now()->addDays(7);
    $renewalOnly = ! $offering->takesNewApplicants();
    $requirements = $offering->formFields->where('field_type', \App\Enums\FieldType::File);

    $primaryClass = 'inline-flex w-full items-center justify-center gap-2 rounded-control bg-navy-600 px-4 py-2.5 font-body text-[14px] font-500 text-white transition-colors hover:bg-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2';
    $sectionHeading = 'flex items-center gap-2 font-display text-[15px] font-600 text-ink';
@endphp

<div>
    <x-ui.back-link :href="route('programs.index')" label="Scholarship programs" wire:navigate />

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_300px] lg:items-start">
        <div class="space-y-4">
            {{-- Header --}}
            <section class="rounded-card border border-line bg-surface p-5">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <x-ui.status-stamp :status="$status" />
                    @if ($closingSoon)
                        <span class="border-l-2 border-amber-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Closing soon</span>
                    @endif
                </div>
                <h1 class="mt-2 font-display text-[22px] font-600 leading-snug text-ink sm:text-[24px]">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 font-body text-[13px] text-slate">
                    <span class="inline-flex items-center gap-1.5"><x-ui.icon name="building" class="size-3.5 text-mist" />{{ $offering->scholarshipProgram->provider->name }}</span>
                    <span class="text-mist">&middot;</span>
                    <span>{{ $offering->scholarshipProgram->provider->funding_source->label() }}</span>
                    <span class="text-mist">&middot;</span>
                    <span>{{ $offering->category->label() }}</span>
                </p>

                @if ($offering->scholarshipProgram->description)
                    <p class="mt-4 whitespace-pre-line border-t border-line pt-4 font-body text-[14px] leading-relaxed text-ink">{{ $offering->scholarshipProgram->description }}</p>
                @endif
            </section>

            {{-- Who can apply --}}
            @if ($this->isCollegeRestricted() || $offering->eligibilityRules->isNotEmpty())
                <section class="rounded-card border border-line bg-surface p-5" aria-labelledby="eligibility-heading">
                    <h2 id="eligibility-heading" class="{{ $sectionHeading }}">
                        <x-ui.icon name="check-circle" class="size-4 text-navy-600" /> Who can apply
                    </h2>

                    @if ($this->isCollegeRestricted())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($offering->offeringColleges as $offeringCollege)
                                <span class="rounded-control border border-line bg-paper px-2 py-1 font-body text-[12px] text-ink">{{ $offeringCollege->college->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($offering->eligibilityRules->isNotEmpty())
                        <ul class="mt-3 space-y-2">
                            @foreach ($offering->eligibilityRules as $rule)
                                <li class="flex items-start gap-2 font-body text-[14px] text-ink">
                                    <x-ui.icon name="check" class="mt-0.5 size-4 text-navy-600" />
                                    {{ $rule->describe() }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            {{-- Documents --}}
            @if ($requirements->isNotEmpty())
                <section class="rounded-card border border-line bg-surface p-5" aria-labelledby="documents-heading">
                    <h2 id="documents-heading" class="{{ $sectionHeading }}">
                        <x-ui.icon name="form" class="size-4 text-navy-600" /> Documents you'll need
                    </h2>
                    <ul class="mt-3 divide-y divide-line">
                        @foreach ($requirements as $requirement)
                            <li class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="min-w-0 font-body text-[14px] text-ink">
                                    {{ $requirement->label }}
                                    @if (! $requirement->is_required) <span class="text-mist">(optional)</span> @endif
                                </span>
                                <span class="shrink-0 font-mono text-[11px] text-mist">{{ str_replace(',', ', ', $requirement->accepted_formats) }} &middot; {{ $requirement->max_size_mb }} MB</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Announcements --}}
            @if ($offering->announcements->isNotEmpty())
                <section class="rounded-card border border-line bg-surface p-5" aria-labelledby="announcements-heading">
                    <h2 id="announcements-heading" class="{{ $sectionHeading }}">
                        <x-ui.icon name="megaphone" class="size-4 text-navy-600" /> Announcements
                    </h2>
                    <div class="mt-3 divide-y divide-line">
                        @foreach ($offering->announcements as $announcement)
                            <article class="py-3 first:pt-0 last:pb-0">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="font-body text-[14px] font-600 text-ink">{{ $announcement->title }}</p>
                                    <p class="font-mono text-[11px] text-mist">{{ $announcement->published_at?->format('M j, Y') }}</p>
                                </div>
                                <p class="mt-1 whitespace-pre-line font-body text-[13px] text-slate">{{ $announcement->body }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Action card --}}
        <aside class="rounded-card border border-line bg-surface lg:sticky lg:top-20">
            <dl class="divide-y divide-line">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="flex items-center gap-2 font-body text-[13px] text-slate"><x-ui.icon name="users" class="size-4 text-mist" />{{ $renewalOnly ? 'Open to' : 'Slots' }}</dt>
                    <dd class="text-right font-body text-[14px] text-ink">
                        @if ($renewalOnly)
                            {{ $offering->renewsOffering?->displayName() }} scholars
                        @else
                            <span class="font-mono">{{ $offering->total_slots }}</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="flex items-center gap-2 font-body text-[13px] text-slate"><x-ui.icon name="clock" class="size-4 text-mist" />{{ $offering->isRenewal() ? 'Submission starts' : 'Opens' }}</dt>
                    <dd class="font-mono text-[13px] text-ink">{{ $offering->opens_at->format('M j, Y') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="flex items-center gap-2 font-body text-[13px] text-slate"><x-ui.icon name="calendar" class="size-4 text-mist" />{{ $offering->isRenewal() ? 'Submission due' : 'Deadline' }}</dt>
                    <dd class="font-mono text-[13px] {{ $closingSoon ? 'text-amber-600' : 'text-ink' }}">{{ $offering->closes_at->format('M j, Y') }}</dd>
                </div>
            </dl>

            <div class="space-y-2 border-t border-line p-5">
                @if ($myApplication)
                    <a href="{{ route('student.applications.show', $myApplication) }}" wire:navigate class="{{ $primaryClass }}"><x-ui.icon name="form" class="size-4" /> View your {{ $myApplication->isRenewal() ? 'renewal' : 'application' }}</a>
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <span class="font-body text-[12px] text-slate">Status</span>
                        <x-ui.status-stamp :application="$myApplication" />
                    </div>
                @elseif (auth()->user() && ! auth()->user()->isStudent())
                    <p class="font-body text-[13px] text-slate">Students apply from their own accounts.</p>
                @elseif ($applyBlocker)
                    <div class="rounded-control border border-line border-l-2 border-l-crimson-600 bg-paper px-3 py-2.5">
                        <p class="flex items-center gap-1.5 font-body text-[13px] font-600 text-crimson-600">
                            <x-ui.icon name="alert-circle" class="size-4" /> You can't apply to this scholarship
                        </p>
                        <p class="mt-1 font-body text-[13px] text-slate">{{ $applyBlocker }}</p>
                    </div>
                    <a href="{{ route('programs.index') }}" wire:navigate class="inline-flex w-full items-center justify-center gap-2 rounded-control border border-line bg-surface px-4 py-2.5 font-body text-[14px] font-500 text-ink transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">Browse other scholarships</a>
                @else
                    <a href="{{ route('programs.apply', $offering) }}" wire:navigate class="{{ $primaryClass }}">Apply now <x-ui.icon name="arrow-right" class="size-4" /></a>
                    @guest
                        <p class="pt-1 font-body text-[12px] text-slate">You'll log in or <a href="{{ route('register') }}" wire:navigate class="font-500 text-navy-700 hover:underline">register</a> first.</p>
                    @endguest
                    @if ($offering->isRenewal())
                        <p class="pt-1 font-body text-[12px] text-slate">Current {{ $offering->renewsOffering?->displayName() }} scholars renew from My applications.</p>
                    @endif
                @endif
            </div>
        </aside>
    </div>
</div>
