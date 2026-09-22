<?php

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusLog;
use App\Services\AwardService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Review Application'))] class extends Component
{
    public Application $application;

    public string $decisionRemarks = '';

    public bool $showAwardForm = false;

    public bool $showForfeitForm = false;

    public string $forfeitReason = '';

    public ?string $successMessage = null;

    public function mount(Application $application): void
    {
        $this->application = $application->load([
            'applicant.applications.programOffering.scholarshipProgram',
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
        $this->successMessage = 'Document verified.';
    }

    public function rejectDocument(int $documentId, string $remarks): void
    {
        if (trim($remarks) === '') {
            $this->addError('remarks', 'Explain why this document is being rejected.');

            return;
        }

        $document = $this->application->documents()->findOrFail($documentId);
        $document->update(['status' => 'rejected', 'verified_by' => auth()->id(), 'verified_at' => now(), 'remarks' => $remarks]);
        $this->application->refresh();
        $this->successMessage = 'Document rejected.';
    }

    public function togglePhysicalReceived(int $documentId): void
    {
        $document = $this->application->documents()
            ->whereHas('offeringFormField', fn ($query) => $query->where('requires_physical_copy', true))
            ->findOrFail($documentId);

        $document->update(['physical_received' => ! $document->physical_received]);
        $this->application->refresh();
    }

    public function endorse(): void
    {
        if ($this->unverifiedDocuments()->isNotEmpty()) {
            $this->addError('decisionRemarks', 'All documents must be verified — with physical copies received where required — before you can endorse this application.');

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
        if (trim($this->decisionRemarks) === '') {
            $this->addError('decisionRemarks', 'Remarks are required.');

            return;
        }

        $from = $this->application->status;
        $this->application->update(['status' => $toStatus]);

        ApplicationStatusLog::create([
            'application_id' => $this->application->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'changed_by' => auth()->id(),
            'reason' => $this->decisionRemarks,
        ]);

        $this->application->refresh();
        $this->decisionRemarks = '';
        $this->successMessage = 'Application '.($toStatus === 'shortlisted' ? 'endorsed.' : 'rejected.');
    }

    public function startAward(): void
    {
        $this->showAwardForm = true;
        $this->resetValidation();
    }

    public function confirmAward(): void
    {
        $service = app(AwardService::class);
        $offering = $this->application->programOffering;
        $collegeId = $offering->allocation_mode->value === 'quota' ? $this->application->college_id : null;

        if ($this->application->outstandingAdditionalRequirements()->isNotEmpty()) {
            $this->addError('slots', 'Mark every additional requirement received before awarding.');

            return;
        }

        if ($service->remainingSlots($offering, $collegeId) <= 0) {
            $this->addError('slots', 'No slots remaining.');

            return;
        }

        $service->create($this->application, auth()->user());

        $this->showAwardForm = false;
        $this->application->refresh();
        $this->successMessage = 'Award recorded and notice sent.';
    }

    public function startForfeit(): void
    {
        $this->showForfeitForm = true;
        $this->forfeitReason = '';
        $this->resetValidation();
    }

    public function confirmForfeit(): void
    {
        if (trim($this->forfeitReason) === '') {
            $this->addError('forfeitReason', 'A reason is required.');

            return;
        }

        app(AwardService::class)->forfeit($this->application->award, auth()->user(), $this->forfeitReason);

        $this->showForfeitForm = false;
        $this->application->refresh();
        $this->successMessage = 'Award forfeited.';
    }

    public function with(): array
    {
        $data = [];

        if ($this->application->status->value === 'shortlisted' && ! $this->application->award) {
            $data['conflictingAward'] = app(AwardService::class)->conflictingAward($this->application);
            $data['outstandingRequirements'] = $this->application->outstandingAdditionalRequirements();
        }

        $data['unverifiedDocuments'] = $this->unverifiedDocuments();

        return $data;
    }
};
?>

<div>
    <x-ui.back-link :href="route('staff.applications.workspace', $application->programOffering)" label="Back to queue" />

    <div class="mt-3 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-[28px] font-600 text-ink">{{ $application->applicant->last_name }}, {{ $application->applicant->first_name }}</h1>
            <p class="mt-1 font-body text-[14px] text-slate">
                <span class="font-mono">{{ $application->reference_no }}</span> &middot; {{ $application->programOffering->scholarshipProgram->name }}
            </p>
        </div>
        <x-ui.status-stamp :status="$application->status" class="shrink-0" />
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-4">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Applicant</p>
                <div class="mt-3 grid grid-cols-2 gap-3 font-body text-[14px] text-ink sm:grid-cols-3">
                    <div><span class="text-slate">Student ID</span><br><span class="font-mono">{{ $application->applicant->student_id }}</span></div>
                    <div><span class="text-slate">Sex</span><br>{{ $application->applicant->sex->label() }}</div>
                    <div><span class="text-slate">Email</span><br>{{ $application->applicant->email }}</div>
                    <div><span class="text-slate">College</span><br>{{ $application->college->name }}</div>
                    <div><span class="text-slate">Year level</span><br>{{ $application->year_level }}</div>
                    <div><span class="text-slate">GWA</span><br><span class="font-mono">{{ $application->gwa ?? '&mdash;' }}</span></div>
                    <div><span class="text-slate">Monthly income</span><br><span class="font-mono">{{ $application->monthly_income ? number_format($application->monthly_income, 2) : '&mdash;' }}</span></div>
                    <div><span class="text-slate">Consent to publish</span><br>{{ $application->consent_publish ? 'Yes' : 'No' }}</div>
                </div>

                @if ($application->fieldResponses->isNotEmpty())
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Additional answers</p>
                        <dl class="mt-2 space-y-2">
                            @foreach ($application->fieldResponses as $response)
                                <div>
                                    <dt class="font-body text-[13px] text-slate">{{ $response->offeringFormField->label }}</dt>
                                    <dd class="font-body text-[14px] text-ink">{{ $response->value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>

            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Documents</p>
                <div class="mt-3 space-y-3">
                    @forelse ($application->documents as $document)
                        <div class="rounded-control border border-line bg-paper p-3 transition hover:border-forest-600" x-data="{ rejecting: false, remarks: '' }">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    @if (str_starts_with((string) $document->mime_type, 'image/'))
                                        <a href="{{ route('staff.applications.documents.show', $document) }}" target="_blank" rel="noopener">
                                            <img src="{{ route('staff.applications.documents.show', $document) }}" alt="{{ $document->offeringFormField->label }}" class="size-12 shrink-0 rounded-control border border-line object-cover">
                                        </a>
                                    @endif
                                    <div>
                                        <p class="font-body text-[14px] text-ink">{{ $document->offeringFormField->label }}</p>
                                        <p class="font-body text-[12px] text-slate">{{ $document->original_name }} &middot; {{ $document->status->label() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('staff.applications.documents.show', $document) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-body text-[13px] font-500 text-forest-700 hover:underline">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                                        View
                                    </a>
                                    @if ($document->offeringFormField->requires_physical_copy)
                                        <label class="flex items-center gap-1 font-body text-[12px] text-slate">
                                            <input
                                                type="checkbox"
                                                class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2"
                                                wire:click="togglePhysicalReceived({{ $document->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="togglePhysicalReceived({{ $document->id }})"
                                                @checked($document->physical_received)
                                            >
                                            Physical copy received
                                        </label>
                                    @endif
                                    @if ($document->status->value !== 'verified')
                                        <button type="button" wire:click="verifyDocument({{ $document->id }})" class="font-body text-[13px] text-forest-700 hover:underline">Verify</button>
                                        <button type="button" @click="rejecting = !rejecting" class="font-body text-[13px] text-crimson-600 hover:underline">Reject</button>
                                    @endif
                                </div>
                            </div>
                            <div x-show="rejecting" x-cloak class="mt-2 flex gap-2">
                                <input type="text" x-model="remarks" placeholder="Reason for rejection" class="flex-1 rounded-control border border-line bg-surface px-2 py-1 text-[13px]">
                                <button type="button" @click="$wire.rejectDocument({{ $document->id }}, remarks)" class="font-body text-[13px] font-500 text-crimson-600 hover:underline">Confirm reject</button>
                            </div>
                        </div>
                    @empty
                        <p class="font-body text-[14px] text-mist">No documents uploaded.</p>
                    @endforelse
                </div>
            </div>

        </div>

        <div class="space-y-6">
            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Screening</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($application->screenings as $screening)
                        <li class="flex items-start gap-2 font-body text-[13px] {{ $screening->passed ? 'text-slate' : 'text-crimson-600' }}">
                            @if ($screening->passed)
                                <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7" /></svg>
                            @else
                                <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                            @endif
                            {{ $screening->message }}
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($application->status->value === 'screening')
                <div class="rounded-card border border-line bg-surface p-5">
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Decision</p>

                    @if (($unverifiedDocuments ?? collect())->isNotEmpty())
                        <p class="mt-2 flex items-start gap-1.5 font-body text-[12px] text-amber-600">
                            <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" /></svg>
                            Verify all documents and confirm physical copies received before you can endorse.
                        </p>
                    @endif

                    <textarea wire:model="decisionRemarks" rows="2" placeholder="Remarks (required)" class="mt-2 w-full rounded-control border border-line bg-paper px-2 py-1 text-[13px]"></textarea>
                    @error('decisionRemarks')<p class="font-body text-[12px] text-crimson-600">{{ $message }}</p>@enderror
                    <div class="mt-2 flex gap-2">
                        <x-ui.button wire:click="endorse" :disabled="($unverifiedDocuments ?? collect())->isNotEmpty()">Endorse</x-ui.button>
                        <x-ui.button variant="danger" wire:click="reject">Reject</x-ui.button>
                    </div>
                </div>
            @endif

            @if ($application->award && $application->award->status->value !== 'terminated')
                @php $award = $application->award; @endphp
                <div class="rounded-card border border-line bg-surface p-5">
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Award</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <x-ui.status-stamp :status="$award->acceptance_status" />
                        <x-ui.status-stamp :status="$award->status" />
                    </div>

                    <div class="mt-3 flex gap-3">
                        <button type="button" wire:click="$set('showForfeitForm', true)" class="font-body text-[13px] text-crimson-600 hover:underline">Forfeit</button>
                    </div>

                    @if ($showForfeitForm)
                        <div class="mt-3 border-t border-line pt-3">
                            <x-ui.textarea name="forfeitReason" wire:model="forfeitReason" rows="2" placeholder="Reason for forfeiture (required)" />
                            <div class="mt-2 flex gap-2">
                                <x-ui.button variant="secondary" type="button" wire:click="$set('showForfeitForm', false)">Cancel</x-ui.button>
                                <x-ui.button variant="danger" wire:click="confirmForfeit">Confirm forfeiture</x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($application->status->value === 'shortlisted')
                <div class="rounded-card border border-line bg-surface p-5">
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Award</p>
                    <p class="mt-2 font-body text-[13px] text-slate">Shortlisted, awaiting award.</p>

                    @if ($conflictingAward ?? null)
                        <p class="mt-2 flex items-center gap-1.5 font-body text-[12px] text-crimson-600">
                            <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" /></svg>
                            Holds another active award ({{ $conflictingAward->programOffering->scholarshipProgram->name ?? 'another offering' }}).
                        </p>
                    @endif

                    @php $outstanding = $outstandingRequirements ?? collect(); @endphp
                    @if ($outstanding->isNotEmpty())
                        <div class="mt-3 border-t border-line pt-3">
                            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Still owed</p>
                            <ul class="mt-1 space-y-1">
                                @foreach ($outstanding as $requirement)
                                    <li class="font-body text-[13px] text-ink">
                                        {{ $requirement->label }}
                                        <span class="text-slate">&middot; {{ $requirement->submission_mode->label() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @error('slots')<p class="mt-2 font-body text-[12px] text-crimson-600">{{ $message }}</p>@enderror

                    @if (! $showAwardForm)
                        <x-ui.button wire:click="startAward" class="mt-3" :disabled="$outstanding->isNotEmpty()">Approve award</x-ui.button>
                    @else
                        <div class="mt-3 border-t border-line pt-3">
                            <div class="flex gap-2">
                                <x-ui.button variant="secondary" type="button" wire:click="$set('showAwardForm', false)">Cancel</x-ui.button>
                                <x-ui.button wire:click="confirmAward">Confirm award</x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if ($application->applicant->applications->count() > 1)
                <div class="rounded-card border border-line bg-surface p-5">
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Other applications</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($application->applicant->applications->where('id', '!=', $application->id) as $other)
                            @php $badge = $other->outcomeBadge(); @endphp
                            <li class="flex items-start justify-between gap-3 font-body text-[13px] text-ink">
                                <span>
                                    {{ $other->programOffering->scholarshipProgram->name }}
                                    <span class="block font-mono text-[12px] text-slate">{{ $other->reference_no }} &middot; {{ $other->status->label() }}</span>
                                </span>
                                <span class="shrink-0 border-l-2 border-{{ $badge['color'] }} pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-{{ $badge['color'] }}">{{ $badge['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Timeline</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($application->statusLogs->sortBy('created_at') as $log)
                        <li class="font-body text-[12px] text-slate">
                            <span class="font-mono">{{ $log->created_at->format('M j, g:ia') }}</span> &mdash; {{ $log->to_status->label() }}
                            @if ($log->changedBy) <span class="text-mist">by {{ $log->changedBy->name }}</span> @endif
                            @if ($log->reason) <span class="block text-mist">&ldquo;{{ $log->reason }}&rdquo;</span> @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
