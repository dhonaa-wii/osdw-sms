<?php

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStage;
use App\Models\Application;
use App\Models\ProgramOffering;
use App\Services\OfferingRequirementService;
use App\Services\RenewalService;
use App\Services\ScholarshipHoldingService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public', array('title' => 'My application', 'noindex' => true))] class extends Component
{
    use WithFileUploads;

    public Application $application;

    /** Pending requirement uploads, keyed by requirement id. */
    public array $uploads = [];

    /** A renewing scholar's year level this term. */
    public string $yearLevel = '';

    public function mount(Application $application): void
    {
        // Students see only their own applications.
        abort_unless($application->isOwnedBy(auth()->user()), 404);

        $this->application = $application->load([
            'applicant', 'programOffering.scholarshipProgram', 'programOffering.academicTerm',
            'statusLogs', 'documents.offeringFormField', 'screenings', 'award',
            'renewedFromAward.programOffering', 'fieldResponses.offeringFormField', 'college',
        ]);

        $this->yearLevel = (string) $this->application->year_level;
    }

    public function saveYearLevel(RenewalService $renewalService): void
    {
        $this->validate(
            ['yearLevel' => ['required', 'integer', 'min:1', 'max:6']],
            ['yearLevel.required' => 'Choose your year level this term.'],
        );

        try {
            $renewalService->setYearLevel($this->application, (int) $this->yearLevel);
        } catch (\RuntimeException $exception) {
            $this->addError('yearLevel', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: 'Year level submitted.');
    }

    public function submitRequirement(int $requirementId, OfferingRequirementService $service): void
    {
        $requirement = $this->application->programOffering->additionalRequirements()
            ->whereIn('stage', array_map(fn ($stage) => $stage->value, $service->activeStages($this->application)))
            ->findOrFail($requirementId);

        abort_unless($requirement->submission_mode->needsUpload(), 404);

        $this->validate([
            "uploads.{$requirementId}" => [
                'required', 'file',
                'mimes:'.$requirement->accepted_formats,
                'max:'.($requirement->max_size_mb * 1024),
            ],
        ], [
            "uploads.{$requirementId}.required" => 'Choose a file to upload first.',
            "uploads.{$requirementId}.mimes" => 'Upload a '.strtoupper(str_replace(',', ', ', $requirement->accepted_formats)).' file.',
            "uploads.{$requirementId}.max" => "Keep the file under {$requirement->max_size_mb} MB.",
        ]);

        try {
            $service->submitUpload($this->application, $requirement, $this->uploads[$requirementId]);
        } catch (\RuntimeException $exception) {
            $this->addError("uploads.{$requirementId}", $exception->getMessage());

            return;
        }

        unset($this->uploads[$requirementId]);
        $this->dispatch('toast', message: "{$requirement->label} submitted. OSDW will review it.");
    }

    public function with(): array
    {
        $service = app(OfferingRequirementService::class);

        $checklist = collect($service->activeStages($this->application))
            ->flatMap(fn ($stage) => $service->checklist($this->application, $stage))
            ->values();

        // Requirements configured for the applicant's stage that staff have not opened yet.
        $offering = $this->application->programOffering;
        $pendingStage = match (true) {
            $this->application->isRenewal() => null,
            $this->application->status === ApplicationStatus::Shortlisted => RequirementStage::Shortlist,
            $this->application->status === ApplicationStatus::Awarded => RequirementStage::Award,
            default => null,
        };

        return [
            'requirementsComing' => $pendingStage !== null
                && ! $offering->hasOpenedRequirements($pendingStage)
                && $offering->additionalRequirements()->where('stage', $pendingStage->value)->exists(),
            'checklist' => $checklist,
            'outstanding' => $checklist->reject(fn ($row) => $row['complied'])->count(),
            'showInterview' => $this->application->status === ApplicationStatus::Shortlisted
                && $this->application->programOffering->hasScheduledInterview(),
            // Awarded another national scholarship meanwhile: this one can't be awarded.
            'blockingAward' => in_array($this->application->status, [ApplicationStatus::Submitted, ApplicationStatus::Screening, ApplicationStatus::Shortlisted], true)
                ? app(ScholarshipHoldingService::class)->blockingAward($this->application)
                : null,
            'announcements' => $offering->announcements()
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->get(),
        ];
    }
};
?>

@php
    $offering = $application->programOffering;
    $status = $application->status;

    $renewalHeadline = $application->isRenewal() ? match ($status->value) {
        'screening' => $outstanding > 0 || $application->year_level === null
            ? ['icon' => 'form', 'tone' => 'amber', 'title' => 'Hand in your requirements to renew your scholarship', 'body' => 'Your scholarship can continue into '.$offering->displayName().'. '.match (true) {
                $application->year_level === null && $outstanding > 0 => 'Enter your year level and complete the '.str('requirement')->plural($outstanding).' below.',
                $application->year_level === null => 'Enter your year level below.',
                default => 'Complete the '.str('requirement')->plural($outstanding).' below.',
            }]
            : ['icon' => 'clock', 'tone' => 'amber', 'title' => 'Your renewal is under review', 'body' => 'OSDW is reviewing your requirements.'],
        'awarded' => ['icon' => 'award', 'tone' => 'gold', 'title' => 'Your scholarship has been renewed', 'body' => match (true) {
            $outstanding > 0 => 'Complete the '.str('requirement')->plural($outstanding).' below so your award can be released.',
            $checklist->isNotEmpty() => 'Your requirements for the award release are complete.',
            default => null,
        }],
        'denied' => ['icon' => 'alert-circle', 'tone' => 'crimson', 'title' => 'Your renewal was not approved', 'body' => 'See the status timeline for the reason.'],
        default => null,
    } : null;

    $headline = $renewalHeadline ?? match ($status->value) {
        'submitted', 'screening' => ['icon' => 'clock', 'tone' => 'amber', 'title' => 'Your application is under review', 'body' => 'OSDW is checking your documents and eligibility. This page updates as your application moves along.'],
        'shortlisted' => ['icon' => 'shortlist', 'tone' => 'navy', 'title' => 'You are on the shortlist', 'body' => match (true) {
            $outstanding > 0 => 'Complete the '.str('requirement')->plural($outstanding).' below to be considered for the award.',
            $requirementsComing => 'OSDW will post the requirements for the award here. Check back soon.',
            default => 'OSDW will announce awardees once slots are filled.',
        }],
        'awarded' => ['icon' => 'award', 'tone' => 'gold', 'title' => 'You have been awarded', 'body' => match (true) {
            $outstanding > 0 => 'Complete the '.str('requirement')->plural($outstanding).' below so your award can be released.',
            $requirementsComing => 'OSDW will post the requirements for the award release here. Check back soon.',
            $checklist->isNotEmpty() => 'Your requirements for the award release are complete.',
            default => null,
        }],
        'waitlisted' => ['icon' => 'clock', 'tone' => 'amber', 'title' => 'You are on the waitlist', 'body' => 'You will be contacted if a slot opens up.'],
        'denied' => ['icon' => 'alert-circle', 'tone' => 'crimson', 'title' => 'Your application was not approved', 'body' => 'See the notes below for the reason.'],
        default => ['icon' => 'form', 'tone' => 'slate', 'title' => $status->label(), 'body' => null],
    };

    $toneClasses = [
        'amber' => 'bg-amber-600 text-white',
        'navy' => 'bg-navy-600 text-white',
        'gold' => 'bg-gold-600 text-white',
        'crimson' => 'bg-crimson-600 text-white',
        'slate' => 'bg-slate text-white',
    ];
@endphp

<div>
    <x-ui.back-link :href="route('student.applications.index')" label="My applications" wire:navigate />

    {{-- Header: which scholarship, where the application stands --}}
    <section class="mt-4 rounded-card border border-line bg-surface" aria-labelledby="status-heading">
        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-paper text-slate">
                    <x-ui.icon :name="$application->isRenewal() ? 'layers' : 'form'" class="size-4" />
                </span>
                <div class="min-w-0">
                    <p class="truncate font-body text-[14px] font-600 text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</p>
                    <p class="truncate font-body text-[12px] text-slate">{{ $application->kind->label() }} &middot; {{ $application->isRenewal() ? 'Opened' : 'Submitted' }} <span class="font-mono">{{ $application->submitted_at?->format('M j, Y') }}</span></p>
                </div>
            </div>
        </div>

        <div class="flex items-start gap-4 px-5 py-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $toneClasses[$headline['tone']] }}">
                <x-ui.icon :name="$headline['icon']" class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <x-ui.status-stamp :application="$application" />
                <h1 id="status-heading" class="mt-1.5 font-display text-[19px] font-600 leading-snug text-ink">{{ $headline['title'] }}</h1>
                @if ($headline['body'])
                    <p class="mt-0.5 font-body text-[14px] text-slate">{{ $headline['body'] }}</p>
                @endif

                @if ($application->award)
                    <div class="mt-3 rounded-control border border-gold-600/40 bg-gold-50 px-3 py-2.5">
                        <p class="flex items-center gap-2 font-body text-[13px] font-500 text-gold-900">
                            <x-ui.icon name="award" class="size-4" />
                            Awarded <span class="font-mono">{{ $application->award->awarded_at->format('M j, Y') }}</span>
                        </p>
                        @if ($offering->results_published && $offering->publish_instructions)
                            <p class="mt-2 whitespace-pre-line border-t border-gold-600/30 pt-2 font-body text-[13px] text-ink">{{ $offering->publish_instructions }}</p>
                        @endif
                    </div>
                @endif

                @if ($blockingAward)
                    <div class="mt-3 border-l-2 border-crimson-600 bg-paper px-3 py-2">
                        <p class="font-body text-[13px] font-600 text-crimson-600">This application can't be awarded</p>
                        <p class="mt-0.5 font-body text-[13px] text-ink">You were awarded {{ $blockingAward->programOffering->scholarshipProgram->name }}, a national scholarship. You can hold only one national scholarship.</p>
                    </div>
                @endif

                @php $failedScreenings = $application->screenings->where('passed', false); @endphp
                @if ($failedScreenings->isNotEmpty())
                    <div class="mt-3 border-l-2 border-crimson-600 bg-paper px-3 py-2">
                        <p class="font-body text-[13px] font-600 text-crimson-600">Screening notes</p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($failedScreenings as $screening)
                                <li class="font-body text-[13px] text-ink">&bull; {{ $screening->message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px] lg:items-start">
        <div class="space-y-4">
            {{-- Renewing scholars confirm their year level every term --}}
            @if ($application->isRenewal() && $status === \App\Enums\ApplicationStatus::Screening)
                <section class="rounded-card border border-line bg-surface px-5 py-4" aria-labelledby="year-level-heading" wire:key="year-level-{{ $application->year_level ?? 'none' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="year-level-heading" class="flex items-center gap-2 font-display text-[15px] font-600 text-ink">
                            <x-ui.icon name="graduation" class="size-4 text-navy-600" /> Your year level this term
                        </h2>
                        @if ($application->year_level === null)
                            <span class="border-l-2 border-amber-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">To submit</span>
                        @else
                            <span class="border-l-2 border-navy-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-navy-700">Submitted</span>
                        @endif
                    </div>

                    @if ($application->year_level !== null)
                        <p class="mt-2 font-body text-[14px] text-ink">Year <span class="font-mono">{{ $application->year_level }}</span></p>
                    @elseif ($offering->acceptsRenewalSubmissions())
                        <form wire:submit="saveYearLevel" class="mt-3">
                            <label for="yearLevel" class="sr-only">Year level</label>
                            <div class="flex gap-2">
                                <div class="relative w-40">
                                    <select id="yearLevel" wire:model.live="yearLevel" required class="h-10 w-full appearance-none rounded-control border border-line bg-surface pl-3 pr-9 font-body text-[14px] text-ink focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25">
                                        <option value="">Select year</option>
                                        @foreach (range(1, 6) as $level)
                                            <option value="{{ $level }}">Year {{ $level }}</option>
                                        @endforeach
                                    </select>
                                    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                                </div>
                                <x-ui.button type="submit" loading="saveYearLevel" class="h-10" wire:confirm="Submit Year {{ $yearLevel ?: '—' }} as your year level? You can't change it afterwards.">Submit year level</x-ui.button>
                            </div>
                            @error('yearLevel')
                                <p class="mt-1.5 font-body text-[13px] text-crimson-600">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 font-body text-[12px] text-mist">You can submit this once.</p>
                            @enderror
                        </form>
                    @else
                        <p class="mt-2 flex items-start gap-1.5 font-body text-[13px] text-slate">
                            <x-ui.icon name="clock" class="mt-0.5 size-4" />
                            <span>{{ now()->lt($offering->opens_at) ? 'You can enter it from '.$offering->opens_at->format('M j, Y · g:i A').'.' : 'Submissions closed on '.$offering->closes_at->format('M j, Y · g:i A').'. Contact the OSDW office.' }}</span>
                        </p>
                    @endif
                </section>
            @endif

            {{-- Additional requirements --}}
            @if ($checklist->isNotEmpty())
                @php
                    $stage = $checklist->first()['requirement']->stage;
                    $done = $checklist->count() - $outstanding;
                @endphp
                <section class="rounded-card border border-line bg-surface" aria-labelledby="requirements-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                        <div class="min-w-0">
                            <h2 id="requirements-heading" class="flex items-center gap-2 font-display text-[15px] font-600 text-ink">
                                <x-ui.icon name="clipboard-check" class="size-4 text-navy-600" />
                                {{ match ($stage) { \App\Enums\RequirementStage::Shortlist => 'Requirements to complete', \App\Enums\RequirementStage::Renewal => 'Requirements to renew', default => 'Requirements for your award release' } }}
                            </h2>
                            @if ($stage === \App\Enums\RequirementStage::Renewal)
                                <p class="mt-0.5 pl-6 font-body text-[12px] text-slate">Submit by <span class="font-mono text-ink">{{ $offering->closes_at->format('M j, Y · g:i A') }}</span></p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-paper" aria-hidden="true">
                                <div class="h-full rounded-full transition-[width] duration-500 {{ $outstanding === 0 ? 'bg-navy-600' : 'bg-amber-600' }}" style="width: {{ round($done / $checklist->count() * 100) }}%"></div>
                            </div>
                            <span class="font-mono text-[12px] text-ink">{{ $done }}/{{ $checklist->count() }}</span>
                        </div>
                    </div>

                    <ol class="divide-y divide-line">
                        @foreach ($checklist as $row)
                            @php
                                $requirement = $row['requirement'];
                                $submission = $row['submission'];
                                $mode = $requirement->submission_mode;
                                $hasFile = (bool) $submission?->file_path;
                                $fileStatus = $hasFile ? $submission->status->value : null;
                                $physicalIn = (bool) $submission?->physical_received_at;

                                [$stampLabel, $stampClass] = match (true) {
                                    $row['complied'] => ['Complied', 'border-navy-600 text-navy-700'],
                                    $fileStatus === 'rejected' => ['Returned', 'border-crimson-600 text-crimson-600'],
                                    $fileStatus === 'pending' => ['Under review', 'border-azure-600 text-azure-600'],
                                    default => ['To submit', 'border-amber-600 text-amber-600'],
                                };

                                $windowOpen = $requirement->stage !== \App\Enums\RequirementStage::Renewal || $offering->acceptsRenewalSubmissions();
                                $submittedOnce = $requirement->stage->isSubmittedOnce();
                                $canUpload = $mode->needsUpload() && $windowOpen && ($submittedOnce
                                    ? (! $hasFile || $fileStatus === 'rejected')
                                    : $fileStatus !== 'verified');
                            @endphp
                            <li class="px-5 py-4" wire:key="track-requirement-{{ $requirement->id }}">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full font-mono text-[12px] transition-colors {{ $row['complied'] ? 'bg-navy-600 text-white' : 'border border-line bg-paper text-slate' }}">
                                        @if ($row['complied'])
                                            <x-ui.icon name="check" class="size-3.5" />
                                            <span class="sr-only">Complete</span>
                                        @else
                                            {{ $loop->iteration }}
                                        @endif
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                            <h3 class="font-body text-[14px] font-600 text-ink">{{ $requirement->label }}</h3>
                                            <span class="border-l-2 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] {{ $stampClass }}">{{ $stampLabel }}</span>
                                        </div>

                                        @if ($requirement->instructions)
                                            <p class="mt-0.5 whitespace-pre-line font-body text-[13px] text-slate">{{ $requirement->instructions }}</p>
                                        @endif

                                        @if ($requirement->template_path)
                                            <a href="{{ route('student.additional-requirements.template', $requirement) }}" class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-control border border-line px-2.5 py-1.5 font-body text-[12px] font-500 text-navy-700 transition-colors hover:border-navy-600 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                                <x-ui.icon name="download" class="size-3.5" />
                                                <span class="shrink-0">Download blank form</span>
                                                <span class="truncate font-400 text-mist">{{ $requirement->template_name }}</span>
                                            </a>
                                        @endif

                                        <div class="mt-2.5 space-y-2">
                                            @if ($mode->needsUpload())
                                                @if ($hasFile)
                                                    <div class="flex items-center gap-2.5 rounded-control border border-line bg-paper px-3 py-2">
                                                        <x-ui.icon name="form" class="size-4 text-slate" />
                                                        <span class="min-w-0 flex-1 truncate font-body text-[13px] text-ink">{{ $submission->original_name }}</span>
                                                        <x-ui.document-link :url="route('student.additional-requirements.show', $submission)" :name="$submission->original_name" :mime="$submission->mime_type" :title="$requirement->label" class="inline-flex shrink-0 items-center gap-1 rounded-control font-body text-[13px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                                            <x-ui.icon name="eye" class="size-3.5" /> View
                                                        </x-ui.document-link>
                                                    </div>
                                                @endif

                                                @if ($fileStatus === 'rejected' && $submission->remarks)
                                                    <p class="flex items-start gap-1.5 font-body text-[13px] text-crimson-600">
                                                        <x-ui.icon name="chat" class="mt-0.5 size-4" />
                                                        <span>OSDW returned this: {{ $submission->remarks }} Upload a corrected file.</span>
                                                    </p>
                                                @elseif ($fileStatus === 'pending')
                                                    <p class="font-body text-[12px] text-azure-600">{{ $submittedOnce ? 'Your file is with OSDW for review.' : 'Your file is with OSDW for review. You can replace it until it is verified.' }}</p>
                                                @endif

                                                @if ($canUpload)
                                                    <div wire:key="upload-{{ $requirement->id }}-{{ $submission?->updated_at?->timestamp ?? 'new' }}">
                                                        <x-ui.file-upload
                                                            name="uploads.{{ $requirement->id }}"
                                                            :label="$hasFile ? 'Replace file' : null"
                                                            :accept="collect(explode(',', $requirement->accepted_formats))->map(fn ($ext) => '.'.$ext)->implode(',')"
                                                            :hint="strtoupper(str_replace(',', ', ', $requirement->accepted_formats)).' · max '.$requirement->max_size_mb.' MB'"
                                                        />
                                                        <div class="mt-2 flex justify-end">
                                                            <x-ui.button
                                                                type="button"
                                                                wire:click="submitRequirement({{ $requirement->id }})"
                                                                loading="submitRequirement({{ $requirement->id }}), uploads.{{ $requirement->id }}"
                                                                class="h-9"
                                                            >
                                                                <x-ui.icon name="upload" class="size-4" /> Submit {{ $requirement->label }}
                                                            </x-ui.button>
                                                        </div>
                                                    </div>
                                                @elseif (! $windowOpen && (! $hasFile || $fileStatus === 'rejected'))
                                                    <p class="flex items-start gap-1.5 font-body text-[13px] text-slate">
                                                        <x-ui.icon name="clock" class="mt-0.5 size-4" />
                                                        <span>{{ now()->lt($offering->opens_at) ? 'Uploads open on '.$offering->opens_at->format('M j, Y · g:i A').'.' : 'Uploads closed on '.$offering->closes_at->format('M j, Y · g:i A').'. Contact the OSDW office.' }}</span>
                                                    </p>
                                                @endif
                                            @endif

                                            @if ($mode->needsPhysical())
                                                <p class="flex items-start gap-2 font-body text-[13px] {{ $physicalIn ? 'text-navy-700' : 'text-slate' }}">
                                                    <x-ui.icon :name="$physicalIn ? 'check-circle' : 'building'" class="mt-0.5 size-4" />
                                                    @if ($physicalIn)
                                                        <span>Physical copy received <span class="font-mono">{{ $submission->physical_received_at->format('M j, Y') }}</span></span>
                                                    @else
                                                        <span>Bring the {{ $mode === \App\Enums\SubmissionMode::Both ? 'original' : 'physical copy' }} to the OSDW office. It is marked received once you hand it in.</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            {{-- What the student submitted, read-only --}}
            @unless ($application->isRenewal())
                <section class="rounded-card border border-line bg-surface" aria-labelledby="submitted-heading">
                    <h2 id="submitted-heading" class="flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink">
                        <x-ui.icon name="graduation" class="size-4 text-navy-600" /> Your submitted form
                    </h2>
                    <dl class="grid grid-cols-1 gap-x-5 gap-y-3 px-5 py-4 sm:grid-cols-2">
                        <div><dt class="font-body text-[12px] text-mist">College</dt><dd class="font-body text-[14px] text-ink">{{ $application->college?->name ?? '—' }}</dd></div>
                        <div><dt class="font-body text-[12px] text-mist">Year level</dt><dd class="font-body text-[14px] text-ink">{{ $application->year_level ? 'Year '.$application->year_level : '—' }}</dd></div>
                        @if ($application->gwa !== null)
                            <div><dt class="font-body text-[12px] text-mist">GWA</dt><dd class="font-mono text-[14px] text-ink">{{ $application->gwa }}</dd></div>
                        @endif
                        @if ($application->monthly_income !== null)
                            <div><dt class="font-body text-[12px] text-mist">Family monthly income</dt><dd class="font-mono text-[14px] text-ink">{{ number_format($application->monthly_income, 2) }}</dd></div>
                        @endif
                        @foreach ($application->fieldResponses as $response)
                            <div wire:key="response-{{ $response->id }}" class="{{ strlen($response->value) > 60 ? 'sm:col-span-2' : '' }}">
                                <dt class="font-body text-[12px] text-mist">{{ $response->offeringFormField->label }}</dt>
                                <dd class="whitespace-pre-line font-body text-[14px] text-ink">{{ $response->value === '1' && $response->offeringFormField->field_type === \App\Enums\FieldType::Checkbox ? 'Yes' : $response->value }}</dd>
                            </div>
                        @endforeach
                        <div class="sm:col-span-2"><dt class="font-body text-[12px] text-mist">Name in published results</dt><dd class="font-body text-[14px] text-ink">{{ $application->consent_publish ? 'May be published' : 'Not published' }}</dd></div>
                    </dl>
                </section>
            @endunless

            {{-- Application documents --}}
            @if ($application->documents->isNotEmpty())
                <section class="rounded-card border border-line bg-surface" aria-labelledby="documents-heading">
                    <h2 id="documents-heading" class="flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink">
                        <x-ui.icon name="form" class="size-4 text-navy-600" /> Application documents
                    </h2>
                    <ul class="divide-y divide-line">
                        @foreach ($application->documents as $document)
                            <li class="flex items-center gap-3 px-5 py-2.5" wire:key="document-{{ $document->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-body text-[14px] text-ink">{{ $document->offeringFormField->label }}</p>
                                    <p class="truncate font-body text-[12px] text-slate">{{ $document->original_name }} &middot; {{ $document->status->label() }}</p>
                                </div>
                                <x-ui.document-link :url="route('student.documents.show', $document)" :name="$document->original_name" :mime="$document->mime_type" :title="$document->offeringFormField->label" class="inline-flex shrink-0 items-center gap-1 rounded-control font-body text-[13px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                    <x-ui.icon name="eye" class="size-3.5" /> View
                                </x-ui.document-link>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Interview --}}
            @if ($showInterview)
                <section class="rounded-card border border-azure-600/40 bg-surface" aria-labelledby="interview-heading">
                    <h2 id="interview-heading" class="flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink">
                        <x-ui.icon name="calendar" class="size-4 text-azure-600" /> Your interview
                    </h2>

                    <div class="flex items-center gap-3 px-5 pt-4">
                        <div class="flex w-12 shrink-0 flex-col items-center overflow-hidden rounded-control border border-line text-center">
                            <span class="w-full bg-azure-600 py-0.5 font-body text-[10px] font-600 text-white">{{ $offering->interview_at->format('M') }}</span>
                            <span class="py-0.5 font-display text-[18px] font-600 leading-tight text-ink">{{ $offering->interview_at->format('j') }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-body text-[14px] font-600 text-ink">{{ $offering->interview_at->format('l, F j, Y') }}</p>
                            <p class="flex items-center gap-1.5 font-mono text-[13px] text-slate">
                                <x-ui.icon name="clock" class="size-3.5" /> {{ $offering->interview_at->format('g:i A') }}
                            </p>
                        </div>
                    </div>

                    <dl class="space-y-2.5 px-5 py-4">
                        @if ($offering->interview_venue)
                            <div class="flex items-start gap-2">
                                <x-ui.icon name="map-pin" class="mt-0.5 size-4 text-slate" />
                                <div>
                                    <dt class="sr-only">Venue</dt>
                                    <dd class="font-body text-[13px] text-ink">{{ $offering->interview_venue }}</dd>
                                </div>
                            </div>
                        @endif
                        @if ($offering->interview_instructions)
                            <div class="flex items-start gap-2">
                                <x-ui.icon name="info" class="mt-0.5 size-4 text-slate" />
                                <div>
                                    <dt class="sr-only">Instructions</dt>
                                    <dd class="whitespace-pre-line font-body text-[13px] text-ink">{{ $offering->interview_instructions }}</dd>
                                </div>
                            </div>
                        @endif
                    </dl>
                </section>
            @endif

            {{-- Timeline --}}
            <section class="rounded-card border border-line bg-surface" aria-labelledby="timeline-heading">
                <h2 id="timeline-heading" class="flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink">
                    <x-ui.icon name="clock" class="size-4 text-navy-600" /> Status timeline
                </h2>
                <ol class="px-5 py-4">
                    @php $disbursed = $application->isDisbursed(); @endphp
                    @if ($disbursed)
                        <li class="relative pb-3 pl-5">
                            <span class="absolute left-[4px] top-3 h-full w-px bg-line" aria-hidden="true"></span>
                            <span class="absolute left-0 top-1.5 size-[9px] rounded-full bg-navy-600 ring-4 ring-navy-50" aria-hidden="true"></span>
                            <p class="font-body text-[13px] font-600 text-ink">Disbursed</p>
                            <p class="font-mono text-[11px] text-mist">{{ $application->award->disbursed_at->format('M j, Y g:ia') }}</p>
                        </li>
                    @endif
                    @foreach ($application->statusLogs->sortByDesc('created_at') as $log)
                        @php $isCurrent = $loop->first && ! $disbursed; @endphp
                        <li class="relative pb-3 pl-5 last:pb-0" wire:key="log-{{ $log->id }}">
                            @unless ($loop->last)
                                <span class="absolute left-[4px] top-3 h-full w-px bg-line" aria-hidden="true"></span>
                            @endunless
                            <span class="absolute left-0 top-1.5 size-[9px] rounded-full {{ $isCurrent ? 'bg-navy-600 ring-4 ring-navy-50' : 'bg-line' }}" aria-hidden="true"></span>
                            <p class="font-body text-[13px] {{ $isCurrent ? 'font-600 text-ink' : 'text-ink' }}">
                                {{ $application->statusLabel($log->to_status) }}
                                @if ($log->reason) <span class="font-400 text-slate">&mdash; {{ $log->reason }}</span> @endif
                            </p>
                            <p class="font-mono text-[11px] text-mist">{{ $log->created_at->format('M j, Y g:ia') }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- This offering's announcements --}}
            @if ($announcements->isNotEmpty())
                <section class="rounded-card border border-line bg-surface" aria-labelledby="announcements-heading">
                    <h2 id="announcements-heading" class="flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink">
                        <x-ui.icon name="megaphone" class="size-4 text-navy-600" /> Announcements
                    </h2>
                    <div class="divide-y divide-line">
                        @foreach ($announcements as $announcement)
                            <article class="px-5 py-3" wire:key="announcement-{{ $announcement->id }}">
                                <p class="font-mono text-[11px] text-mist">{{ $announcement->published_at?->format('M j, Y') }}</p>
                                <p class="mt-0.5 font-body text-[14px] font-600 text-ink">{{ $announcement->title }}</p>
                                <p class="mt-1 whitespace-pre-line font-body text-[13px] text-slate">{{ $announcement->body }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
