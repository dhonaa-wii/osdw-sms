<?php

use App\Enums\DocumentStatus;
use App\Enums\RequirementStage;
use App\Models\Award;
use App\Services\AwardService;
use App\Services\OfferingRequirementService;
use App\Services\RenewalService;
use App\Services\ScholarshipHoldingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Grantee'))] class extends Component
{
    public Award $award;

    public function mount(Award $award): void
    {
        $this->award = $award->load(['applicant', 'programOffering.scholarshipProgram.provider', 'programOffering.academicTerm']);
    }

    /**
     * Disburse one award of this grantee's chain — the latest one or an
     * earlier term's that was never recorded.
     */
    public function disburse(int $awardId, AwardService $awardService, RenewalService $renewalService): void
    {
        $award = $renewalService->historyOf($this->award)->pluck('award')->filter()->firstWhere('id', $awardId);

        abort_unless($award, 404);

        try {
            $awardService->disburse($award, auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t disburse', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->dispatch('notice', title: 'Grantee disbursed', message: $this->award->applicant->first_name.' '.$this->award->applicant->last_name.' is marked disbursed.');
    }

    public function with(RenewalService $renewalService, OfferingRequirementService $requirementService, AwardService $awardService): array
    {
        // Each application in the chain with the requirements of its own
        // stages: the application form, then shortlist and disbursement
        // requirements for the original application, renewal requirements
        // for each renewal.
        $history = $renewalService->historyOf($this->award)->map(fn ($application) => [
            'application' => $application,
            'disbursementBlocker' => $application->award ? $awardService->disbursementBlocker($application->award) : null,
            'checklists' => collect($application->isRenewal() ? [RequirementStage::Renewal] : RequirementStage::forApplicants())
                ->map(fn (RequirementStage $stage) => ['stage' => $stage, 'rows' => $requirementService->checklist($application, $stage)])
                ->filter(fn ($group) => $group['rows']->isNotEmpty())
                ->values(),
        ]);

        // Applications outside this chain, so staff see the student's other
        // programs and awards.
        $otherApplications = $this->award->applicant->applications()
            ->whereNotIn('id', $history->pluck('application.id'))
            ->with(['programOffering.scholarshipProgram', 'award'])
            ->latest()
            ->get();

        return [
            'history' => $history,
            'otherApplications' => $otherApplications,
            'heldAwards' => app(ScholarshipHoldingService::class)->heldAwards($this->award->applicant_id, $this->award->programOffering),
        ];
    }
};
?>

@php
    $applicant = $award->applicant;
    $latest = $history->last()['application'] ?? null;
@endphp

<div>
    <x-ui.back-link :href="route('staff.grantees.index')" label="Grantees" wire:navigate />

    @if ($latest)
        <x-staff.review-header class="mt-3" :application="$latest" :applicant="$applicant" />
    @endif

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3" x-data="{ entry: {{ $latest?->id ?? 'null' }} }">
        {{-- Side column: who the student is and every record in this scholarship. Comes first on small screens. --}}
        <div class="space-y-5 lg:order-2">
            <x-staff.review-card icon="user" title="Student information">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3.5">
                    <x-staff.review-fact label="Student ID"><span class="font-mono">{{ $applicant->student_id }}</span></x-staff.review-fact>
                    <x-staff.review-fact label="Sex">{{ $applicant->sex->label() }}</x-staff.review-fact>
                    <x-staff.review-fact label="Birthdate"><span class="font-mono">{{ $applicant->birthdate?->format('M j, Y') ?? '—' }}</span></x-staff.review-fact>
                    <x-staff.review-fact label="Mobile"><span class="font-mono">{{ $applicant->mobile ?: '—' }}</span></x-staff.review-fact>
                    <div class="col-span-2">
                        <x-staff.review-fact label="Email" class="break-all">{{ $applicant->email ?: '—' }}</x-staff.review-fact>
                    </div>
                    <div class="col-span-2">
                        <x-staff.review-fact label="Address">{{ $applicant->address ?: '—' }}</x-staff.review-fact>
                    </div>
                    @if ($latest)
                        <x-staff.review-fact label="College">{{ $latest->college->name }}</x-staff.review-fact>
                        <x-staff.review-fact label="Year level"><span class="font-mono">{{ $latest->year_level ?? '—' }}</span></x-staff.review-fact>
                    @endif
                </dl>
            </x-staff.review-card>

            <x-staff.review-card icon="layers" title="Scholarship history">
                <x-slot:meta>
                    <span class="font-mono text-[12px] text-mist">{{ $history->count() }}</span>
                </x-slot:meta>

                <ol class="divide-y divide-line" aria-label="Applications and renewals">
                    @foreach ($history as $entry)
                        @php $record = $entry['application']; @endphp
                        <li wire:key="history-nav-{{ $record->id }}">
                            <button
                                type="button"
                                x-on:click="entry = {{ $record->id }}"
                                x-bind:aria-current="entry === {{ $record->id }} ? 'true' : null"
                                x-bind:class="entry === {{ $record->id }} ? 'border-l-navy-600 bg-navy-50/60' : 'border-l-transparent hover:bg-paper/60'"
                                class="flex w-full items-center gap-3 border-l-2 px-4 py-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-600"
                            >
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full {{ $record->award ? 'bg-gold-600/10 text-gold-600' : 'bg-paper text-mist' }}">
                                    <x-ui.icon :name="$record->award ? 'award' : ($record->isRenewal() ? 'clipboard-check' : 'form')" class="size-3.5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-body text-[13px] font-500 text-ink">{{ $record->isRenewal() ? 'Renewal' : 'Application' }} &middot; {{ $record->programOffering->displayName() }}</span>
                                    <span class="block truncate font-body text-[12px] text-mist">{{ $record->programOffering->academicTerm->school_year }} &middot; {{ $record->programOffering->academicTerm->semester->label() }} &middot; {{ $record->statusLabel() }}</span>
                                </span>
                                <x-ui.icon name="chevron-right" class="size-4 text-mist" />
                            </button>
                        </li>
                    @endforeach
                </ol>
            </x-staff.review-card>

            <x-staff.scholarships-held :awards="$heldAwards" />

            <x-staff.other-applications :applications="$otherApplications" />
        </div>

        {{-- Main column: the selected application or renewal. --}}
        <div class="min-w-0 lg:order-1 lg:col-span-2">
            @foreach ($history as $entry)
                @php
                    $application = $entry['application'];
                    $documents = $application->documents;
                    $verifiedCount = $documents->where('status', DocumentStatus::Verified)->count();
                @endphp
                <div wire:key="history-{{ $application->id }}" x-show="entry === {{ $application->id }}" @if ($application->id !== $latest?->id) x-cloak @endif class="space-y-5">
                    <x-staff.review-card :icon="$application->isRenewal() ? 'clipboard-check' : 'form'" :title="($application->isRenewal() ? 'Renewal' : 'Application').' · '.$application->programOffering->scholarshipProgram->name.' · '.$application->programOffering->displayName()">
                        <x-slot:meta>
                            <x-ui.status-stamp :application="$application" />
                        </x-slot:meta>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3.5 sm:grid-cols-3">
                            <x-staff.review-fact label="Term">{{ $application->programOffering->academicTerm->school_year }} &middot; {{ $application->programOffering->academicTerm->semester->label() }}</x-staff.review-fact>
                            <x-staff.review-fact label="College">{{ $application->college->name }}</x-staff.review-fact>
                            <x-staff.review-fact label="Year level"><span class="font-mono">{{ $application->year_level ?? '—' }}</span></x-staff.review-fact>
                            @unless ($application->isRenewal())
                                <x-staff.review-fact label="GWA"><span class="font-mono">{{ $application->gwa ?? '—' }}</span></x-staff.review-fact>
                                <x-staff.review-fact label="Family monthly income"><span class="font-mono">{{ $application->monthly_income ? '₱'.number_format($application->monthly_income, 2) : '—' }}</span></x-staff.review-fact>
                                <x-staff.review-fact label="Consent to publish">{{ $application->consent_publish ? 'Yes' : 'No' }}</x-staff.review-fact>
                            @endunless
                            <x-staff.review-fact label="Submitted"><span class="font-mono">{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</span></x-staff.review-fact>
                        </dl>

                        @if ($application->fieldResponses->isNotEmpty())
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 border-t border-line px-4 py-3.5 sm:grid-cols-2">
                                @foreach ($application->fieldResponses as $response)
                                    <x-staff.review-fact :label="$response->offeringFormField->label">{{ $response->value ?: '—' }}</x-staff.review-fact>
                                @endforeach
                            </dl>
                        @endif
                    </x-staff.review-card>

                    @if ($documents->isNotEmpty() || $entry['checklists']->isNotEmpty())
                        <x-staff.review-card icon="form" title="Documents">
                            @if ($documents->isNotEmpty())
                            <x-staff.document-group title="Application documents" icon="form" :complied="$verifiedCount" :total="$documents->count()" verb="verified">
                                @foreach ($documents as $document)
                                    <x-staff.document-item
                                        :url="route('staff.applications.documents.show', $document)"
                                        :name="$document->original_name"
                                        :mime="$document->mime_type"
                                        :label="$document->offeringFormField->label"
                                        wire:key="document-{{ $document->id }}-{{ crc32($document->file_path) }}"
                                    >
                                        <x-slot:meta>
                                            <span class="shrink-0 {{ match ($document->status) { DocumentStatus::Verified => 'text-navy-700', DocumentStatus::Rejected => 'text-crimson-600', default => 'text-amber-600' } }}">{{ $document->status->label() }}</span>
                                            <span class="text-mist">&middot;</span>
                                            <span class="truncate text-mist">{{ $document->original_name }}</span>
                                        </x-slot:meta>

                                        @if ($document->offeringFormField->requires_physical_copy)
                                            <x-slot:actions>
                                                <x-staff.physical-copy-toggle :received="$document->physical_received" />
                                            </x-slot:actions>
                                        @endif
                                    </x-staff.document-item>
                                @endforeach
                            </x-staff.document-group>
                            @endif

                            @foreach ($entry['checklists'] as $group)
                                <x-staff.requirement-checklist :stage="$group['stage']" :rows="$group['rows']" readonly />
                            @endforeach
                        </x-staff.review-card>
                    @endif

                    @if ($application->award)
                        @php $applicationAward = $application->award; @endphp
                        <div class="flex flex-wrap items-center gap-3 rounded-card border border-line border-l-2 border-l-gold-600 bg-surface px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon name="award" class="size-4 text-gold-600" />
                                    <p class="font-body text-[13px] text-slate">{{ $application->isRenewal() ? 'Renewed' : 'Awarded' }} <span class="font-mono text-[12px]">{{ $applicationAward->awarded_at->format('M j, Y') }}</span></p>
                                </div>
                                <p class="mt-1 font-mono text-[20px] text-ink">{{ $applicationAward->amount ? '₱'.number_format($applicationAward->amount, 2) : '—' }}</p>
                            </div>

                            @if ($applicationAward->isDisbursed())
                                <div class="text-right">
                                    <span class="border-l-2 border-gold-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-gold-600">Disbursed</span>
                                    <p class="mt-1 font-body text-[12px] text-mist">
                                        <span class="font-mono">{{ $applicationAward->disbursed_at->format('M j, Y') }}</span>
                                        @if ($applicationAward->disbursedBy) &middot; {{ $applicationAward->disbursedBy->name }} @endif
                                    </p>
                                </div>
                            @else
                                <span @if ($entry['disbursementBlocker']) title="{{ $entry['disbursementBlocker'] }}" @endif>
                                    <x-ui.button
                                        class="px-3 py-1.5 text-[13px]"
                                        loading="disburse({{ $applicationAward->id }})"
                                        :disabled="$entry['disbursementBlocker'] !== null"
                                        x-on:click="$store.confirm.ask({
                                            title: 'Disburse this grantee?',
                                            message: 'The award is recorded as disbursed. This can’t be undone.',
                                            confirmLabel: 'Disburse',
                                            tone: 'primary',
                                            action: () => $wire.disburse({{ $applicationAward->id }}),
                                        })"
                                    >
                                        <x-ui.icon name="award" class="size-3.5" />
                                        Disburse
                                    </x-ui.button>
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
