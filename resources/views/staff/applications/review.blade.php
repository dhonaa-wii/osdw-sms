<?php

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStage;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusLog;
use App\Services\AwardService;
use App\Services\OfferingRequirementService;
use App\Services\ScholarshipHoldingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Review Application'))] class extends Component
{
    public Application $application;

    public string $decisionRemarks = '';

    public function mount(Application $application): void
    {
        // Renewals have their own review page (renewal requirements only).
        if ($application->isRenewal()) {
            throw new HttpResponseException(new RedirectResponse(route('staff.renewals.review', $application)));
        }

        $this->application = $application->load([
            'applicant.applications.programOffering.scholarshipProgram',
            'applicant.applications.programOffering.academicTerm',
            'applicant.applications.award',
            'applicant.awards',
            'programOffering.scholarshipProgram',
            'college',
            'documents.offeringFormField',
            'fieldResponses.offeringFormField',
            'screenings.eligibilityRule',
            'statusLogs.changedBy',
            'award',
        ]);
    }

    public function verifyDocument(int $documentId): void
    {
        $document = $this->application->documents()->findOrFail($documentId);
        $document->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
        $this->application->refresh();
    }

    /** Returns whether the document was rejected, so the reason field can close. */
    public function rejectDocument(int $documentId, string $remarks): bool
    {
        if (trim($remarks) === '') {
            $this->dispatch('notice', title: 'Reason needed', message: 'Explain why this document is being rejected.', tone: 'error');

            return false;
        }

        $document = $this->application->documents()->findOrFail($documentId);
        $document->update(['status' => 'rejected', 'verified_by' => auth()->id(), 'verified_at' => now(), 'remarks' => trim($remarks)]);
        $this->application->refresh();
        $this->dispatch('notice', title: 'Document rejected', message: $document->offeringFormField->label);

        return true;
    }

    public function togglePhysicalReceived(int $documentId): void
    {
        $document = $this->application->documents()
            ->whereHas('offeringFormField', fn ($query) => $query->where('requires_physical_copy', true))
            ->findOrFail($documentId);

        $document->update(['physical_received' => ! $document->physical_received]);
        $this->application->refresh();
    }

    public function verifyRequirement(int $submissionId, OfferingRequirementService $service): void
    {
        $service->verify($this->application->additionalRequirementSubmissions()->findOrFail($submissionId), auth()->user());
    }

    public function rejectRequirement(int $submissionId, string $remarks, OfferingRequirementService $service): bool
    {
        if (trim($remarks) === '') {
            $this->dispatch('notice', title: 'Reason needed', message: 'Tell the applicant what to fix before returning the upload.', tone: 'error');

            return false;
        }

        $service->reject($this->application->additionalRequirementSubmissions()->findOrFail($submissionId), trim($remarks), auth()->user());
        $this->dispatch('notice', title: 'Upload returned', message: 'The applicant can upload a corrected file from their tracking page.');

        return true;
    }

    public function togglePhysicalRequirement(int $requirementId, OfferingRequirementService $service): void
    {
        $service->togglePhysicalReceived(
            $this->application,
            $this->application->programOffering->additionalRequirements()->findOrFail($requirementId),
            auth()->user(),
        );
    }

    public function endorse(): void
    {
        if ($this->unverifiedDocuments()->isNotEmpty()) {
            $this->dispatch('notice', title: 'Documents not verified', message: 'Verify every document — with physical copies received where required — before endorsing.', tone: 'error');

            return;
        }

        if ($blocker = app(ScholarshipHoldingService::class)->awardBlocker($this->application)) {
            $this->dispatch('notice', title: 'Can\'t endorse', message: $blocker, tone: 'error');

            return;
        }

        $this->decide('shortlisted');
    }

    public function reject(): void
    {
        $this->decide('denied');
    }

    protected function unverifiedDocuments(): Collection
    {
        return $this->application->documents->filter(function (ApplicationDocument $document) {
            if ($document->status->value !== 'verified') {
                return true;
            }

            return $document->offeringFormField->requires_physical_copy && ! $document->physical_received;
        });
    }

    protected function decide(string $toStatus): void
    {
        $from = $this->application->status;
        $this->application->update(['status' => $toStatus]);

        ApplicationStatusLog::create([
            'application_id' => $this->application->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'changed_by' => auth()->id(),
            'reason' => trim($this->decisionRemarks) ?: null,
        ]);

        $this->application->refresh();
        $this->decisionRemarks = '';
        $this->dispatch(
            'notice',
            title: $toStatus === 'shortlisted' ? 'Application endorsed' : 'Application rejected',
            message: $toStatus === 'shortlisted' ? 'The applicant is now on the shortlist.' : 'The applicant can see the outcome on their tracking page.',
        );
    }

    public function award(): void
    {
        $service = app(AwardService::class);
        $offering = $this->application->programOffering;
        $collegeId = $offering->allocation_mode->value === 'quota' ? $this->application->college_id : null;

        if ($service->remainingSlots($offering, $collegeId) <= 0) {
            $this->dispatch('notice', title: 'No slots remaining', message: 'Every slot for this offering is already awarded.', tone: 'error');

            return;
        }

        if (app(OfferingRequirementService::class)->outstandingCount($this->application, RequirementStage::Shortlist) > 0) {
            $this->dispatch('notice', title: 'Requirements outstanding', message: 'Verify every shortlist requirement before awarding.', tone: 'error');

            return;
        }

        try {
            $service->create($this->application, auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t award', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->application->refresh();
        $this->dispatch('notice', title: 'Award recorded', message: 'The applicant has been emailed the award notice.');
    }

    public function with(): array
    {
        $holdings = app(ScholarshipHoldingService::class);

        // Scholarships held in other programs, and whether one of them is a
        // national scholarship that rules this application out.
        $data = [
            'heldAwards' => $holdings->heldAwards($this->application->applicant_id, $this->application->programOffering),
            'nationalBlocker' => $this->application->award ? null : $holdings->awardBlocker($this->application),
        ];

        $data['unverifiedDocuments'] = $this->unverifiedDocuments();

        // Shortlisted: the requirements gating the award. Awarded: those for
        // the record, then the disbursement requirements.
        $requirementService = app(OfferingRequirementService::class);
        $stages = match ($this->application->status) {
            ApplicationStatus::Shortlisted => [RequirementStage::Shortlist],
            ApplicationStatus::Awarded => [RequirementStage::Shortlist, RequirementStage::Award],
            default => [],
        };

        $data['requirementChecklists'] = collect($stages)
            ->map(fn (RequirementStage $stage) => ['stage' => $stage, 'rows' => $requirementService->checklist($this->application, $stage)])
            ->filter(fn ($group) => $group['rows']->isNotEmpty())
            ->values();

        $data['outstandingShortlist'] = $this->application->status === ApplicationStatus::Shortlisted
            ? $requirementService->outstandingCount($this->application, RequirementStage::Shortlist)
            : 0;

        return $data;
    }
};
?>

@php
    $applicant = $application->applicant;
    $offering = $application->programOffering;
    $fullName = $applicant->first_name.' '.$applicant->last_name;
    $verifiedCount = $application->documents->where('status', \App\Enums\DocumentStatus::Verified)->count();
    $otherApplications = $applicant->applications->where('id', '!=', $application->id)->sortByDesc('created_at');
@endphp

<div>
    <x-ui.back-link
        :href="route('staff.applications.workspace', ['offering' => $offering, 'tab' => $application->status === ApplicationStatus::Shortlisted ? 'shortlist' : 'queue'])"
        :label="$offering->scholarshipProgram->name.' · '.$offering->displayName()"
        wire:navigate
    />

    <x-staff.review-header class="mt-3" :application="$application" :applicant="$applicant" />

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-staff.review-card icon="user" title="Applicant details">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3.5 sm:grid-cols-3">
                    <x-staff.review-fact label="Email">{{ $applicant->email }}</x-staff.review-fact>
                    <x-staff.review-fact label="Sex">{{ $applicant->sex->label() }}</x-staff.review-fact>
                    <x-staff.review-fact label="College">{{ $application->college->name }}</x-staff.review-fact>
                    <x-staff.review-fact label="Year level"><span class="font-mono">{{ $application->year_level }}</span></x-staff.review-fact>
                    <x-staff.review-fact label="GWA"><span class="font-mono">{{ $application->gwa ?? '—' }}</span></x-staff.review-fact>
                    <x-staff.review-fact label="Family monthly income"><span class="font-mono">{{ $application->monthly_income ? number_format($application->monthly_income, 2) : '—' }}</span></x-staff.review-fact>
                    <x-staff.review-fact label="Consent to publish">{{ $application->consent_publish ? 'Yes' : 'No' }}</x-staff.review-fact>
                    <x-staff.review-fact label="Submitted"><span class="font-mono">{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</span></x-staff.review-fact>
                </dl>

                @if ($application->fieldResponses->isNotEmpty())
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-3 border-t border-line px-4 py-3.5 sm:grid-cols-2">
                        @foreach ($application->fieldResponses as $response)
                            <x-staff.review-fact :label="$response->offeringFormField->label">{{ $response->value }}</x-staff.review-fact>
                        @endforeach
                    </dl>
                @endif
            </x-staff.review-card>

            <x-staff.review-card icon="form" title="Documents">
                <x-staff.document-group title="Application documents" icon="form" :complied="$verifiedCount" :total="$application->documents->count()" verb="verified">
                    @forelse ($application->documents as $document)
                        @php
                            $label = $document->offeringFormField->label;
                            $statusTone = match ($document->status->value) { 'verified' => 'text-navy-700', 'rejected' => 'text-crimson-600', default => 'text-amber-600' };
                        @endphp
                        <x-staff.document-item
                            :url="route('staff.applications.documents.show', $document)"
                            :name="$document->original_name"
                            :mime="$document->mime_type"
                            :label="$label"
                            wire:key="document-{{ $document->id }}-{{ crc32($document->file_path) }}"
                            x-data="{ rejecting: false, remarks: '' }"
                        >
                            <x-slot:meta>
                                <span class="shrink-0 {{ $statusTone }}">{{ $document->status->label() }}</span>
                                <span class="text-mist">&middot;</span>
                                <span class="truncate text-mist">{{ $document->status->value === 'rejected' && $document->remarks ? $document->remarks : $document->original_name }}</span>
                            </x-slot:meta>

                            <x-slot:actions>
                                @if ($document->offeringFormField->requires_physical_copy)
                                    <x-staff.physical-copy-toggle :received="$document->physical_received" action="togglePhysicalReceived({{ $document->id }})" />
                                @endif
                                @if ($document->status->value !== 'verified')
                                    @if ($document->status->value !== 'rejected')
                                        <x-ui.button variant="secondary" class="h-8 px-3 py-0 text-[13px] text-crimson-600" x-on:click="rejecting = ! rejecting; $nextTick(() => $refs.reason?.focus())">
                                            <x-ui.icon name="x" class="size-3.5" /> Reject
                                        </x-ui.button>
                                    @endif
                                    <x-ui.button class="h-8 px-3 py-0 text-[13px]" wire:click="verifyDocument({{ $document->id }})" loading="verifyDocument({{ $document->id }})">
                                        <x-ui.icon name="check" class="size-3.5" /> Verify
                                    </x-ui.button>
                                @endif
                            </x-slot:actions>

                            <x-staff.reason-form
                                show="rejecting"
                                submit="if (await $wire.rejectDocument({{ $document->id }}, remarks)) { rejecting = false; remarks = ''; }"
                                :id="'reject-remarks-'.$document->id"
                                :label="'Reason for rejecting '.$label"
                                placeholder="What the applicant needs to fix"
                                cancel="rejecting = false"
                                loading="rejectDocument"
                            >Reject document</x-staff.reason-form>
                        </x-staff.document-item>
                    @empty
                        <x-ui.list-empty icon="form" title="No documents uploaded" class="py-8" />
                    @endforelse
                </x-staff.document-group>

                @foreach ($requirementChecklists as $group)
                    <x-staff.requirement-checklist :stage="$group['stage']" :rows="$group['rows']" />
                @endforeach
            </x-staff.review-card>
        </div>

        <div class="space-y-5">
            <x-staff.scholarships-held :awards="$heldAwards" :blocker="$nationalBlocker" />

            @if ($application->status === ApplicationStatus::Screening)
                @php $blocked = $unverifiedDocuments->isNotEmpty(); @endphp
                <x-staff.review-card icon="clipboard-check" title="Decision">
                    <div class="space-y-3 px-4 py-3.5">
                        @if ($nationalBlocker)
                            <p class="flex items-start gap-1.5 font-body text-[12px] text-crimson-600">
                                <x-ui.icon name="alert-circle" class="mt-px size-4 shrink-0" />
                                Already holds a national scholarship, so this application can't be endorsed.
                            </p>
                        @elseif ($blocked)
                            <p class="flex items-start gap-1.5 font-body text-[12px] text-amber-600">
                                <x-ui.icon name="alert-circle" class="mt-px size-4 shrink-0" />
                                Verify every document and confirm physical copies to endorse.
                            </p>
                        @endif

                        <x-ui.textarea name="decisionRemarks" label="Remarks" wire:model="decisionRemarks" rows="2" placeholder="Optional — saved on the timeline" />

                        <div class="grid grid-cols-2 gap-2">
                            <x-ui.button
                                variant="secondary"
                                class="text-crimson-600"
                                loading="reject"
                                x-on:click="$store.confirm.ask({
                                    title: {{ \Illuminate\Support\Js::from('Reject '.$fullName.'?') }},
                                    message: 'The application leaves the queue. This can’t be undone.',
                                    confirmLabel: 'Reject application',
                                    action: () => $wire.reject(),
                                })"
                            >
                                <x-ui.icon name="x" class="size-4" />
                                Reject
                            </x-ui.button>
                            <x-ui.button
                                loading="endorse"
                                :disabled="$blocked || $nationalBlocker"
                                x-on:click="$store.confirm.ask({
                                    title: {{ \Illuminate\Support\Js::from('Endorse '.$fullName.'?') }},
                                    message: 'The application moves to the shortlist.',
                                    confirmLabel: 'Endorse application',
                                    tone: 'primary',
                                    action: () => $wire.endorse(),
                                })"
                            >
                                <x-ui.icon name="check" class="size-4" />
                                Endorse
                            </x-ui.button>
                        </div>
                    </div>
                </x-staff.review-card>
            @endif

            @if ($application->award)
                <div class="rounded-card border border-line border-l-2 border-l-gold-600 bg-surface px-4 py-3.5">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="award" class="size-4 text-gold-600" />
                        <p class="font-body text-[13px] text-slate">Awarded <span class="font-mono text-[12px]">{{ $application->award->awarded_at->format('M j, Y') }}</span></p>
                    </div>
                    <p class="mt-1 font-mono text-[20px] text-ink">₱{{ number_format($application->award->amount, 2) }}</p>
                </div>
            @elseif ($application->status === ApplicationStatus::Shortlisted)
                <x-staff.review-card icon="award" title="Award">
                    <div class="space-y-3 px-4 py-3.5">
                        @if ($nationalBlocker)
                            <p class="flex items-start gap-1.5 font-body text-[12px] text-crimson-600">
                                <x-ui.icon name="alert-circle" class="mt-px size-4 shrink-0" />
                                Already holds a national scholarship, so this application can't be awarded.
                            </p>
                        @endif

                        @if ($outstandingShortlist > 0)
                            <p class="flex items-start gap-1.5 font-body text-[12px] text-amber-600">
                                <x-ui.icon name="clock" class="mt-px size-4 shrink-0" />
                                {{ $outstandingShortlist }} shortlist {{ str('requirement')->plural($outstandingShortlist) }} still to comply with.
                            </p>
                        @endif

                        <x-ui.button
                            class="w-full"
                            loading="award"
                            :disabled="$outstandingShortlist > 0 || $nationalBlocker"
                            x-on:click="$store.confirm.ask({
                                title: {{ \Illuminate\Support\Js::from('Award '.$fullName.'?') }},
                                message: 'The award is recorded and the notice is emailed right away. This can’t be undone.',
                                confirmLabel: 'Record award',
                                tone: 'primary',
                                action: () => $wire.award(),
                            })"
                        >
                            <x-ui.icon name="award" class="size-4" />
                            Award scholarship
                        </x-ui.button>
                    </div>
                </x-staff.review-card>
            @endif

            <x-staff.review-card icon="check-square" title="Screening">
                <ul class="space-y-2 px-4 py-3.5">
                    @forelse ($application->screenings as $screening)
                        <li class="flex items-start gap-2 font-body text-[13px] {{ $screening->passed ? 'text-slate' : 'text-crimson-600' }}">
                            <x-ui.icon :name="$screening->passed ? 'check' : 'x'" class="mt-0.5 size-3.5 shrink-0" />
                            {{ $screening->message }}
                        </li>
                    @empty
                        <li class="font-body text-[13px] text-mist">No eligibility checks on this offering.</li>
                    @endforelse
                </ul>
            </x-staff.review-card>

            <x-staff.other-applications :applications="$otherApplications" />

            <x-staff.status-timeline :application="$application" />
        </div>
    </div>
</div>
