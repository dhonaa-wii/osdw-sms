<?php

use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusLog;
use App\Enums\RequirementSubmission;
use App\Models\ScholarTerm;
use App\Services\AdditionalRequirementService;
use App\Services\AwardService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public', array('title' => 'My Applications', 'noindex' => true))] class extends Component
{
    use WithFileUploads;

    public Applicant $applicant;

    public array $replacementUploads = [];

    public array $requirementUploads = [];

    public array $gradeGwa = [];

    public array $gradeUnits = [];

    public array $gradeFailing = [];

    public array $nonWithdrawableStatuses = ['withdrawn', 'denied', 'forfeited', 'approved', 'accepted'];

    public function mount(): void
    {
        $applicantId = session('tracking_applicant_id');

        abort_unless($applicantId, 403);

        $this->applicant = Applicant::findOrFail($applicantId);
    }

    public function withdraw(int $applicationId): void
    {
        $application = $this->applicant->applications()->findOrFail($applicationId);

        if (in_array($application->status->value, $this->nonWithdrawableStatuses, true)) {
            return;
        }

        $from = $application->status;
        $application->update(['status' => 'withdrawn']);

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'from_status' => $from,
            'to_status' => 'withdrawn',
            'reason' => 'Withdrawn by applicant.',
        ]);
    }

    public function replaceDocument(int $documentId): void
    {
        $document = ApplicationDocument::whereHas(
            'application',
            fn ($query) => $query->where('applicant_id', $this->applicant->id)
        )->findOrFail($documentId);

        if ($document->status->value === 'verified') {
            return;
        }

        $this->validate([
            "replacementUploads.{$documentId}" => ['required', 'file', 'max:'.($document->offeringFormField->max_size_mb * 1024), 'mimes:'.$document->offeringFormField->accepted_formats],
        ]);

        $file = $this->replacementUploads[$documentId];
        $path = $file->store("applications/{$this->applicant->student_id}/{$document->offering_form_field_id}", 'local');

        Storage::disk('local')->delete($document->file_path);

        $document->update([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
        ]);

        unset($this->replacementUploads[$documentId]);
    }

    /**
     * A shortlisted applicant handing in one of the offering's additional
     * requirements. Only the upload kind arrives this way — a physical-copy item is
     * ticked off by staff at the counter.
     */
    public function submitRequirement(int $applicationId, int $requirementId): void
    {
        $application = $this->applicant->applications()
            ->where('status', 'shortlisted')
            ->findOrFail($applicationId);

        $requirement = $application->programOffering->additionalRequirements()
            ->where('submission_mode', RequirementSubmission::Upload)
            ->findOrFail($requirementId);

        $this->validate([
            "requirementUploads.{$requirementId}" => [
                'required', 'file',
                'max:'.(AdditionalRequirementService::MAX_SIZE_MB * 1024),
                'mimes:'.AdditionalRequirementService::ACCEPTED_FORMATS,
            ],
        ], attributes: ["requirementUploads.{$requirementId}" => $requirement->label]);

        app(AdditionalRequirementService::class)->submitUpload(
            $application,
            $requirement,
            $this->requirementUploads[$requirementId],
        );

        unset($this->requirementUploads[$requirementId]);
    }

    public function acceptAward(int $awardId): void
    {
        $award = $this->applicant->awards()->where('acceptance_status', 'pending')->findOrFail($awardId);
        app(AwardService::class)->accept($award);
    }

    public function declineAward(int $awardId): void
    {
        $award = $this->applicant->awards()->where('acceptance_status', 'pending')->findOrFail($awardId);
        app(AwardService::class)->decline($award);
    }

    public function applyForRenewal(int $awardId): void
    {
        $award = $this->applicant->awards()->where('status', 'active')->findOrFail($awardId);
        $currentTerm = AcademicTerm::where('is_current', true)->first();

        if (! $currentTerm) {
            return;
        }

        ScholarTerm::firstOrCreate(
            ['award_id' => $award->id, 'academic_term_id' => $currentTerm->id],
            ['applicant_id' => $this->applicant->id, 'status' => 'active']
        );
    }

    public function submitGrades(int $awardId): void
    {
        $award = $this->applicant->awards()->where('status', 'active')->findOrFail($awardId);
        $currentTerm = AcademicTerm::where('is_current', true)->first();

        if (! $currentTerm) {
            return;
        }

        $this->validate([
            "gradeGwa.{$awardId}" => ['required', 'numeric', 'min:0', 'max:100'],
            "gradeUnits.{$awardId}" => ['required', 'integer', 'min:0'],
        ]);

        ScholarTerm::updateOrCreate(
            ['award_id' => $award->id, 'academic_term_id' => $currentTerm->id],
            [
                'applicant_id' => $this->applicant->id,
                'gwa' => $this->gradeGwa[$awardId],
                'units_enrolled' => $this->gradeUnits[$awardId],
                'has_failing_grade' => ! empty($this->gradeFailing[$awardId]),
                'status' => 'active',
            ]
        );

        unset($this->gradeGwa[$awardId], $this->gradeUnits[$awardId], $this->gradeFailing[$awardId]);
    }

    public function logout(): void
    {
        session()->forget('tracking_applicant_id');
        $this->redirect(route('track.lookup'));
    }

    public function with(): array
    {
        return [
            'applications' => $this->applicant->applications()
                ->with([
                    'programOffering.scholarshipProgram', 'programOffering.academicTerm', 'programOffering.additionalRequirements',
                    'statusLogs', 'documents.offeringFormField', 'screenings', 'additionalRequirementReceipts',
                    'award.scholarTerms.academicTerm',
                ])
                ->orderByDesc('submitted_at')
                ->get(),
            'currentTerm' => AcademicTerm::where('is_current', true)->first(),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-[28px] font-600 text-ink">My applications</h1>
            <p class="mt-1 font-body text-[15px] text-slate">{{ $applicant->first_name }} {{ $applicant->last_name }} &middot; <span class="font-mono">{{ $applicant->student_id }}</span></p>
            <a href="{{ route('programs.index') }}" class="mt-2 inline-block font-body text-[13px] font-500 text-forest-700 hover:underline">Browse other scholarships &rarr;</a>
        </div>
        <button type="button" wire:click="logout" class="font-body text-[13px] text-slate transition-colors hover:text-crimson-600 hover:underline">Log out</button>
    </div>

    <div class="mt-8 space-y-6">
        @foreach ($applications as $application)
            <div class="rounded-card border border-line bg-surface p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-display text-[18px] font-600 text-ink">{{ $application->programOffering->scholarshipProgram->name }}</p>
                        <p class="mt-0.5 font-body text-[13px] text-slate">{{ $application->programOffering->displayName() }}</p>
                        <p class="mt-1 font-mono text-[13px] text-slate">{{ $application->reference_no }}</p>
                    </div>
                    <x-ui.status-stamp :status="$application->status" class="shrink-0" />
                </div>

                @if ($application->award)
                    @php $award = $application->award; @endphp
                    <div class="mt-4 rounded-control border border-forest-600 bg-forest-50 px-4 py-3">
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-forest-700">Award</p>
                        <p class="mt-1 font-body text-[14px] text-ink">
                            @if ($award->amount) Amount: <span class="font-mono">&#8369;{{ number_format($award->amount, 2) }}</span> @endif
                            @if ($award->discount_rate) Tuition discount: <span class="font-mono">{{ $award->discount_rate }}%</span> @endif
                        </p>

                        @if ($award->acceptance_status->value === 'pending')
                            <p class="mt-1 font-body text-[13px] text-slate">Declining frees the slot for another applicant.</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="acceptAward({{ $award->id }})" class="rounded-control bg-forest-600 px-3 py-1.5 font-body text-[13px] font-500 text-white hover:bg-forest-700">Accept award</button>
                                <button type="button" wire:click="declineAward({{ $award->id }})" wire:confirm="Decline this award? This cannot be undone." class="rounded-control border border-line bg-surface px-3 py-1.5 font-body text-[13px] font-500 text-ink hover:border-crimson-600">Decline</button>
                            </div>
                        @elseif ($award->acceptance_status->value === 'accepted')
                            <p class="mt-1 font-body text-[13px] text-forest-700">Accepted on {{ $award->accepted_at->format('M j, Y') }}.</p>
                        @endif

                        @if ($award->status->value === 'active' && $award->acceptance_status->value === 'accepted')
                            @php $currentTermRecord = $currentTerm ? $award->scholarTerms->firstWhere('academic_term_id', $currentTerm->id) : null; @endphp

                            @if ($currentTerm && ! $currentTermRecord)
                                <div class="mt-3 border-t border-line pt-3">
                                    <p class="font-body text-[13px] font-500 text-ink">Renew for {{ $currentTerm->school_year }} {{ $currentTerm->semester->label() }}?</p>
                                    <p class="mt-1 font-body text-[13px] text-slate">Apply to continue this grant into the new term, then submit your grades.</p>
                                    <button type="button" wire:click="applyForRenewal({{ $award->id }})" class="mt-2 rounded-control bg-forest-600 px-3 py-1.5 font-body text-[13px] font-500 text-white hover:bg-forest-700">Apply for renewal</button>
                                </div>
                            @elseif ($currentTermRecord && $currentTermRecord->gwa === null)
                                <div class="mt-3 border-t border-line pt-3">
                                    <p class="font-body text-[13px] font-500 text-ink">Submit grades &mdash; {{ $currentTerm->school_year }} {{ $currentTerm->semester->label() }}</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        <input type="number" step="0.01" min="0" max="100" wire:model="gradeGwa.{{ $award->id }}" placeholder="GWA" class="rounded-control border border-line bg-surface px-2 py-1 text-[13px]">
                                        <input type="number" min="0" wire:model="gradeUnits.{{ $award->id }}" placeholder="Units enrolled" class="rounded-control border border-line bg-surface px-2 py-1 text-[13px]">
                                        <label class="flex items-center gap-1 font-body text-[13px] text-ink">
                                            <input type="checkbox" wire:model="gradeFailing.{{ $award->id }}"> Failing grade
                                        </label>
                                    </div>
                                    @error("gradeGwa.{$award->id}")<p class="mt-1 font-body text-[12px] text-crimson-600">{{ $message }}</p>@enderror
                                    @error("gradeUnits.{$award->id}")<p class="mt-1 font-body text-[12px] text-crimson-600">{{ $message }}</p>@enderror
                                    <button type="button" wire:click="submitGrades({{ $award->id }})" class="mt-2 rounded-control bg-forest-600 px-3 py-1.5 font-body text-[13px] font-500 text-white hover:bg-forest-700">Submit grades</button>
                                </div>
                            @endif

                            @if ($award->scholarTerms->isNotEmpty())
                                <div class="mt-3 border-t border-line pt-3">
                                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Term history</p>
                                    <ul class="mt-1 space-y-1">
                                        @foreach ($award->scholarTerms->sortByDesc('created_at') as $term)
                                            <li class="font-body text-[13px] text-slate">
                                                {{ $term->academicTerm->school_year }} {{ $term->academicTerm->semester->label() }}
                                                &mdash; GWA <span class="font-mono">{{ $term->gwa ?? 'pending' }}</span>
                                                &middot; {{ $term->status->label() }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                @php $additionalRequirements = $application->status->value === 'shortlisted' ? $application->programOffering->additionalRequirements : collect(); @endphp
                @if ($additionalRequirements->isNotEmpty())
                    <div class="mt-4 border-l-2 border-amber-600 bg-paper px-4 py-3">
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Documents needed</p>
                        <ul class="mt-2 space-y-4">
                            @foreach ($additionalRequirements as $requirement)
                                @php $receipt = $application->additionalRequirementReceipts->firstWhere('offering_additional_requirement_id', $requirement->id); @endphp
                                <li wire:key="needed-{{ $application->id }}-{{ $requirement->id }}">
                                    <p class="font-body text-[14px] font-500 text-ink">{{ $requirement->label }}</p>

                                    @if ($receipt)
                                        <p class="mt-0.5 font-body text-[13px] text-forest-700">
                                            Received on {{ $receipt->received_at->format('M j, Y') }}@if ($receipt->original_name) &middot; {{ $receipt->original_name }}@endif
                                        </p>
                                    @else
                                        <p class="mt-0.5 font-body text-[13px] text-slate">{{ $requirement->submission_mode->instruction() }}</p>
                                    @endif

                                    @if ($requirement->submission_mode->isUpload())
                                        <div class="mt-2 w-full sm:w-80">
                                            <x-ui.file-upload
                                                name="requirementUploads.{{ $requirement->id }}"
                                                :accept="'.pdf,.jpg,.jpeg,.png'"
                                                :hint="'PDF, JPG or PNG · up to '.\App\Services\AdditionalRequirementService::MAX_SIZE_MB.'MB'"
                                            />
                                            <button
                                                type="button"
                                                wire:click="submitRequirement({{ $application->id }}, {{ $requirement->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="submitRequirement({{ $application->id }}, {{ $requirement->id }})"
                                                class="mt-2 rounded-control bg-forest-600 px-3 py-1.5 font-body text-[13px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                <span wire:loading.remove wire:target="submitRequirement({{ $application->id }}, {{ $requirement->id }})">{{ $receipt ? 'Replace file' : 'Submit document' }}</span>
                                                <span wire:loading wire:target="submitRequirement({{ $application->id }}, {{ $requirement->id }})">Submitting…</span>
                                            </button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php $failedScreenings = $application->screenings->where('passed', false); @endphp
                @if ($failedScreenings->isNotEmpty())
                    <div class="mt-4 border-l-2 border-crimson-600 bg-paper px-4 py-3">
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-crimson-600">Screening notes</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($failedScreenings as $screening)
                                <li class="font-body text-[13px] text-ink">&bull; {{ $screening->message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-4">
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Status timeline</p>
                    <div class="mt-2">
                        @foreach ($application->statusLogs->sortBy('created_at') as $log)
                            <div class="relative border-l-2 border-line py-1 pb-3 pl-4 last:border-transparent last:pb-0">
                                <span class="absolute -left-[5px] top-2 size-2 rounded-full bg-forest-600"></span>
                                <p class="font-body text-[13px] text-ink">
                                    {{ $log->to_status->label() }}
                                    @if ($log->reason) <span class="text-slate">&mdash; {{ $log->reason }}</span> @endif
                                </p>
                                <p class="font-mono text-[12px] text-mist">{{ $log->created_at->format('M j, Y g:ia') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($application->documents->isNotEmpty())
                    <div class="mt-4">
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Documents</p>
                        <div class="mt-2 space-y-3">
                            @foreach ($application->documents as $document)
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-control border border-line bg-paper px-3 py-2">
                                    <div>
                                        <p class="font-body text-[14px] text-ink">{{ $document->offeringFormField->label }}</p>
                                        <p class="font-body text-[12px] text-slate">{{ $document->original_name }} &middot; {{ $document->status->label() }}</p>
                                    </div>
                                    @if ($document->status->value !== 'verified')
                                        <div class="w-full sm:w-64">
                                            <x-ui.file-upload
                                                name="replacementUploads.{{ $document->id }}"
                                                :existing-name="$document->original_name"
                                            />
                                            <button type="button" wire:click="replaceDocument({{ $document->id }})" class="mt-1 font-body text-[13px] text-forest-700 hover:underline">Replace</button>
                                        </div>
                                    @endif
                                </div>
                                @error("replacementUploads.{$document->id}")
                                    <p class="font-body text-[13px] text-crimson-600">{{ $message }}</p>
                                @enderror
                            @endforeach
                        </div>
                    </div>
                @endif

                @unless (in_array($application->status->value, $nonWithdrawableStatuses, true))
                    <div class="mt-4 border-t border-line pt-4">
                        <button type="button" wire:click="withdraw({{ $application->id }})" wire:confirm="Withdraw this application? This cannot be undone." class="font-body text-[13px] text-crimson-600 hover:underline">Withdraw application</button>
                    </div>
                @endunless
            </div>
        @endforeach
    </div>
</div>
