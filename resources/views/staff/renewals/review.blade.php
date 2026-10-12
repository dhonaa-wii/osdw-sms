<?php

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStage;
use App\Models\Application;
use App\Services\AwardService;
use App\Services\OfferingRequirementService;
use App\Services\RenewalService;
use App\Services\ScholarshipHoldingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Review Renewal'))] class extends Component
{
    public Application $application;

    public string $decisionRemarks = '';

    public function mount(Application $application): void
    {
        abort_unless($application->isRenewal(), 404);

        $this->application = $application;
    }

    public function verifyRequirement(int $submissionId, OfferingRequirementService $service): void
    {
        $service->verify($this->application->additionalRequirementSubmissions()->findOrFail($submissionId), auth()->user());
    }

    public function rejectRequirement(int $submissionId, string $remarks, OfferingRequirementService $service): bool
    {
        if (trim($remarks) === '') {
            $this->dispatch('notice', title: 'Reason needed', message: 'Tell the scholar what to fix before returning the upload.', tone: 'error');

            return false;
        }

        $service->reject($this->application->additionalRequirementSubmissions()->findOrFail($submissionId), trim($remarks), auth()->user());
        $this->dispatch('notice', title: 'Upload returned', message: 'The scholar can upload a corrected file from their tracking page.');

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

    public function renew(AwardService $awardService): void
    {
        try {
            $awardService->create($this->application, auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t renew', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->application->refresh();
        $this->dispatch('notice', title: 'Scholarship renewed', message: 'The scholar has been emailed the renewal notice.');
    }

    public function deny(RenewalService $renewalService): void
    {
        $this->resetValidation();

        if (trim($this->decisionRemarks) === '') {
            $this->addError('decisionRemarks', 'Give the reason, e.g. "Graduated" or "Has a failing grade".');

            return;
        }

        try {
            $renewalService->deny($this->application, trim($this->decisionRemarks), auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t deny', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->application->refresh();
        $this->decisionRemarks = '';
        $this->dispatch('notice', title: 'Renewal denied', message: 'The scholar can see the reason on their tracking page.');
    }

    public function with(OfferingRequirementService $requirementService): array
    {
        $this->application->load([
            'applicant.applications.programOffering.scholarshipProgram',
            'applicant.applications.award',
            'programOffering.scholarshipProgram',
            'programOffering.renewsOffering',
            'college',
            'renewedFromAward.programOffering',
            'statusLogs.changedBy',
            'award',
        ]);

        return [
            'checklist' => $requirementService->checklist($this->application, RequirementStage::Renewal),
            'heldAwards' => app(ScholarshipHoldingService::class)->heldAwards($this->application->applicant_id, $this->application->programOffering),
        ];
    }
};
?>

@php
    $offering = $application->programOffering;
    $applicant = $application->applicant;
    $fullName = $applicant->first_name.' '.$applicant->last_name;
    $otherApplications = $applicant->applications->where('id', '!=', $application->id)->sortByDesc('created_at');
@endphp

<div>
    <x-ui.back-link :href="route('staff.renewals.index', $offering)" :label="$offering->scholarshipProgram->name.' · '.$offering->displayName()" wire:navigate />

    <x-staff.review-header class="mt-3" :application="$application" :applicant="$applicant" />

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-staff.review-card icon="user" title="Scholar details">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3.5 sm:grid-cols-3">
                    <x-staff.review-fact label="Email">{{ $applicant->email }}</x-staff.review-fact>
                    <x-staff.review-fact label="College">{{ $application->college->name }}</x-staff.review-fact>
                    <x-staff.review-fact label="Year level this term">
                        @if ($application->year_level === null)
                            <span class="text-amber-600">Not entered yet</span>
                        @else
                            <span class="font-mono">{{ $application->year_level }}</span>
                        @endif
                    </x-staff.review-fact>
                    <x-staff.review-fact label="Renewing from">{{ $application->renewedFromAward?->programOffering->displayName() ?? $offering->renewsOffering->displayName() }}</x-staff.review-fact>
                </dl>
            </x-staff.review-card>

            <x-staff.review-card icon="form" title="Documents">
                @if ($checklist->isNotEmpty())
                    <x-staff.requirement-checklist :stage="RequirementStage::Renewal" :rows="$checklist" />
                @else
                    <x-ui.list-empty icon="clipboard-check" title="No renewal requirements" message="The administrator adds renewal requirements on this offering's Requirements page.">
                        @if (auth()->user()->role === \App\Enums\UserRole::Admin)
                            <x-ui.button-link variant="secondary" :href="route('admin.offerings.configure', $offering)" wire:navigate>Add renewal requirements</x-ui.button-link>
                        @endif
                    </x-ui.list-empty>
                @endif
            </x-staff.review-card>
        </div>

        <div class="space-y-5">
            <x-staff.scholarships-held :awards="$heldAwards" />

            @if ($application->status === ApplicationStatus::Screening)
                @php
                    $outstanding = $checklist->reject(fn ($row) => $row['complied'])->count();
                    $blocked = $outstanding > 0 || $application->year_level === null;
                @endphp
                <x-staff.review-card icon="clipboard-check" title="Decision">
                    <div class="space-y-3 px-4 py-3.5">
                        @if ($outstanding > 0)
                            <p class="flex items-start gap-1.5 font-body text-[12px] text-amber-600">
                                <x-ui.icon name="clock" class="mt-px size-4 shrink-0" />
                                {{ $outstanding }} {{ str('requirement')->plural($outstanding) }} still to comply before you can renew.
                            </p>
                        @endif

                        @if ($application->year_level === null)
                            <p class="flex items-start gap-1.5 font-body text-[12px] text-amber-600">
                                <x-ui.icon name="alert-circle" class="mt-px size-4 shrink-0" />
                                The scholar enters their year level on their tracking page before you can renew.
                            </p>
                        @endif

                        <x-ui.button
                            class="w-full"
                            loading="renew"
                            :disabled="$blocked"
                            x-on:click="$store.confirm.ask({
                                title: {{ \Illuminate\Support\Js::from('Renew '.$fullName.'?') }},
                                message: 'The scholar is emailed right away. This can’t be undone.',
                                confirmLabel: 'Renew scholarship',
                                tone: 'primary',
                                action: () => $wire.renew(),
                            })"
                        >
                            <x-ui.icon name="check" class="size-4" />
                            Renew scholarship
                        </x-ui.button>

                        <div class="border-t border-line pt-3">
                            <x-ui.textarea name="decisionRemarks" label="Reason for denying" wire:model="decisionRemarks" rows="2" placeholder="Graduated, has a failing grade, no longer enrolled" compact />
                            <x-ui.button
                                variant="secondary"
                                class="mt-2 w-full text-crimson-600"
                                loading="deny"
                                x-on:click="$wire.decisionRemarks.trim() === ''
                                    ? $wire.deny()
                                    : $store.confirm.ask({
                                        title: {{ \Illuminate\Support\Js::from('Deny '.$fullName.'’s renewal?') }},
                                        message: 'The scholar sees the reason on their tracking page. This can’t be undone.',
                                        confirmLabel: 'Deny renewal',
                                        action: () => $wire.deny(),
                                    })"
                            >
                                <x-ui.icon name="x" class="size-4" />
                                Deny renewal
                            </x-ui.button>
                        </div>
                    </div>
                </x-staff.review-card>
            @elseif ($application->award)
                <div class="rounded-card border border-line border-l-2 border-l-gold-600 bg-surface px-4 py-3.5">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="award" class="size-4 text-gold-600" />
                        <p class="font-body text-[13px] text-slate">Renewed <span class="font-mono text-[12px]">{{ $application->award->awarded_at->format('M j, Y') }}</span></p>
                    </div>
                    <p class="mt-1 font-mono text-[20px] text-ink">₱{{ number_format($application->award->amount, 2) }}</p>
                </div>
            @endif

            <x-staff.other-applications :applications="$otherApplications" />

            <x-staff.status-timeline :application="$application" />
        </div>
    </div>
</div>
