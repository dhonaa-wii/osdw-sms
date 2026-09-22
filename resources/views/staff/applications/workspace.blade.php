<?php

use App\Enums\EmailType;
use App\Enums\RequirementSubmission;
use App\Mail\IncompleteApplicationMail;
use App\Models\Application;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Services\AdditionalRequirementService;
use App\Services\AwardService;
use App\Services\EmailService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Applications'))] class extends Component
{
    public ProgramOffering $offering;

    public string $search = '';

    public string $college_id = '';

    public string $shortlist_college_id = '';

    public array $selected = [];

    public ?int $awardingApplicationId = null;

    public bool $showRequirementsModal = false;

    /** @var array<int, array{label: string, submission_mode: string}> */
    public array $requirementLines = [['label' => '', 'submission_mode' => 'upload']];

    public ?string $successMessage = null;

    protected array $awardedStatuses = ['approved', 'accepted'];

    /**
     * Statuses that are still mid-screening: requirements review only. Once
     * an applicant is endorsed or rejected they move to `shortlisted` or
     * `denied` and leave this queue for good.
     */
    protected array $screeningStatuses = ['submitted', 'screening'];

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm', 'offeringColleges.college');
    }

    public function bulkVerify(): void
    {
        $count = 0;

        foreach (Application::whereIn('id', $this->selected)->with('documents')->get() as $application) {
            foreach ($application->documents->where('status', 'pending') as $document) {
                $document->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
                $count++;
            }
        }

        $this->selected = [];
        $this->successMessage = $count > 0
            ? "Verified {$count} pending document(s)."
            : 'No pending documents on the selected applications.';
    }

    public function bulkNotifyIncomplete(): void
    {
        $emailService = app(EmailService::class);
        $notified = 0;

        $applications = Application::whereIn('id', $this->selected)
            ->whereHas('documents', fn ($query) => $query->where('status', 'rejected'))
            ->with('applicant', 'documents.offeringFormField', 'programOffering.scholarshipProgram')
            ->get();

        foreach ($applications as $application) {
            $emailService->send($application, EmailType::Reminder, new IncompleteApplicationMail($application), $application->applicant->email);
            $notified++;
        }

        $this->selected = [];
        $this->successMessage = $notified > 0
            ? "Notified {$notified} applicant(s) with a rejected document."
            : 'None of the selected applications have a rejected document.';
    }

    public function openRequirements(): void
    {
        $this->showRequirementsModal = true;
        $this->requirementLines = [$this->blankRequirementLine()];
        $this->resetValidation();
    }

    public function closeRequirements(): void
    {
        $this->showRequirementsModal = false;
        $this->requirementLines = [$this->blankRequirementLine()];
        $this->resetValidation();
    }

    public function addRequirementLine(): void
    {
        $this->requirementLines[] = $this->blankRequirementLine();
    }

    public function removeRequirementLine(int $index): void
    {
        unset($this->requirementLines[$index]);

        $this->requirementLines = array_values($this->requirementLines) ?: [$this->blankRequirementLine()];
    }

    /**
     * @return array{label: string, submission_mode: string}
     */
    protected function blankRequirementLine(): array
    {
        return ['label' => '', 'submission_mode' => RequirementSubmission::Upload->value];
    }

    public function saveRequirements(): void
    {
        $named = collect($this->requirementLines)->filter(fn ($line) => trim((string) ($line['label'] ?? '')) !== '');

        if ($named->isEmpty()) {
            $this->addError('requirementLines', 'Name at least one requirement.');

            return;
        }

        if ($named->contains(fn ($line) => mb_strlen(trim((string) $line['label'])) > 255)) {
            $this->addError('requirementLines', 'Keep each requirement under 255 characters.');

            return;
        }

        $added = app(AdditionalRequirementService::class)->add($this->offering, $named->all(), auth()->user());

        $this->closeRequirements();
        $this->successMessage = match ($added->count()) {
            0 => 'Those requirements are already on the list.',
            1 => 'Added 1 requirement for every shortlisted applicant.',
            default => "Added {$added->count()} requirements for every shortlisted applicant.",
        };
    }

    /**
     * Unticking always works — it is how staff reject an upload or correct a
     * mistake. Ticking only works for a physical copy handed over the counter;
     * an upload becomes received when the applicant's file lands.
     */
    public function toggleRequirementReceived(int $applicationId, int $requirementId): void
    {
        $application = $this->offering->applications()->findOrFail($applicationId);
        $requirement = $this->offering->additionalRequirements()->findOrFail($requirementId);

        $service = app(AdditionalRequirementService::class);

        if ($application->hasReceivedAdditionalRequirement($requirement)) {
            $service->markOutstanding($application, $requirement);

            return;
        }

        if ($requirement->submission_mode->isUpload()) {
            $this->successMessage = "Waiting on the applicant to upload \"{$requirement->label}\".";

            return;
        }

        $service->markReceived($application, $requirement, auth()->user());
    }

    public function removeRequirement(int $requirementId): void
    {
        $requirement = $this->offering->additionalRequirements()->findOrFail($requirementId);

        app(AdditionalRequirementService::class)->remove($requirement);

        $this->successMessage = 'Requirement removed from this offering.';
    }

    public function startAward(int $applicationId): void
    {
        $this->awardingApplicationId = $applicationId;
        $this->resetValidation();
    }

    public function confirmAward(): void
    {
        $application = Application::with('additionalRequirementReceipts', 'programOffering.additionalRequirements')
            ->findOrFail($this->awardingApplicationId);
        $service = app(AwardService::class);
        $collegeId = $this->offering->allocation_mode->value === 'quota' ? $application->college_id : null;

        if ($application->outstandingAdditionalRequirements()->isNotEmpty()) {
            $this->addError('slots', 'Mark every additional requirement received before awarding.');

            return;
        }

        if ($service->remainingSlots($this->offering, $collegeId) <= 0) {
            $this->addError('slots', 'No slots remaining.');

            return;
        }

        $service->create($application, auth()->user());

        $this->awardingApplicationId = null;
        $this->successMessage = 'Award recorded and notice sent.';
    }

    public function with(): array
    {
        $queue = $this->offering->applications()
            ->whereIn('status', $this->screeningStatuses)
            ->withCount(['screenings as failed_screenings_count' => fn ($query) => $query->where('passed', false)])
            ->with(['applicant.applications.award', 'college'])
            ->when($this->search, fn ($query) => $query->where(function ($query) {
                $query->where('reference_no', 'like', "%{$this->search}%")
                    ->orWhereHas('applicant', fn ($query) => $query->where('last_name', 'like', "%{$this->search}%")
                        ->orWhere('first_name', 'like', "%{$this->search}%")
                        ->orWhere('student_id', 'like', "%{$this->search}%"));
            }))
            ->when($this->college_id, fn ($query) => $query->where('college_id', $this->college_id))
            ->orderBy('failed_screenings_count')
            ->orderByDesc('submitted_at')
            ->get();

        $shortlistService = app(AwardService::class);

        $additionalRequirements = $this->offering->additionalRequirements()->orderBy('id')->get();

        $shortlist = $this->offering->applications()
            ->with(['applicant', 'college', 'additionalRequirementReceipts'])
            ->where('status', 'shortlisted')
            ->when($this->shortlist_college_id, fn ($query) => $query->where('college_id', $this->shortlist_college_id))
            ->orderBy('college_id')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn ($application) => [
                'application' => $application,
                'conflict' => $shortlistService->conflictingAward($application),
                'received' => $application->additionalRequirementReceipts->pluck('offering_additional_requirement_id'),
            ]);

        $slotCounters = collect();

        if ($this->offering->allocation_mode->value === 'quota') {
            foreach ($this->offering->offeringColleges as $offeringCollege) {
                $used = $this->offering->applications()
                    ->where('college_id', $offeringCollege->college_id)
                    ->whereIn('status', $this->awardedStatuses)
                    ->count();

                $slotCounters->push([
                    'label' => $offeringCollege->college->name,
                    'used' => $used,
                    'total' => $offeringCollege->slots,
                ]);
            }
        } else {
            $used = $this->offering->applications()->whereIn('status', $this->awardedStatuses)->count();
            $slotCounters->push(['label' => 'Overall', 'used' => $used, 'total' => $this->offering->total_slots]);
        }

        return [
            'queue' => $queue,
            'shortlist' => $shortlist,
            'slotCounters' => $slotCounters,
            'colleges' => College::whereIn('id', $this->offering->applications()->distinct()->pluck('college_id'))->get(),
            'additionalRequirements' => $additionalRequirements,
        ];
    }
};
?>

<div x-data="{ tab: 'queue' }">
    <x-ui.back-link :href="route('staff.applications.index')" label="Applications" />

    <div class="mt-3 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-[28px] font-600 text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>
        </div>
        <div class="flex shrink-0 gap-4">
            <a href="{{ route('staff.awards.workspace', $offering) }}" class="font-body text-[14px] font-500 text-forest-700 hover:underline">Awards &rarr;</a>
            <a href="{{ route('staff.scholars.workspace', $offering) }}" class="font-body text-[14px] font-500 text-forest-700 hover:underline">Scholars &rarr;</a>
        </div>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-4">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-{{ $slotCounters->count() > 1 ? 3 : 1 }}">
        @foreach ($slotCounters as $counter)
            @php $percent = $counter['total'] > 0 ? min(100, round($counter['used'] / $counter['total'] * 100)) : 0; @endphp
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" /><path d="M16 8.5a3 3 0 1 0 0-6" /><path d="M17.5 14.3c2.3.6 4 2.7 4 5.7" /></svg>
                    </span>
                    <div>
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">{{ $counter['label'] }} slots</p>
                        <p class="font-mono text-[20px] leading-tight text-ink">{{ $counter['used'] }} / {{ $counter['total'] }}</p>
                    </div>
                </div>
                <div class="mt-3 h-1.5 rounded-full bg-paper">
                    <div class="h-1.5 rounded-full {{ $percent >= 100 ? 'bg-crimson-600' : 'bg-forest-600' }}" style="width: {{ $percent }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-1 border-b border-line">
        <button type="button" @click="tab = 'queue'" :class="tab === 'queue' ? 'border-forest-600 text-forest-700' : 'border-transparent text-slate'" class="border-b-2 px-4 py-2 font-body text-[14px] font-500 transition-colors hover:text-forest-700">Queue</button>
        <button type="button" @click="tab = 'shortlist'" :class="tab === 'shortlist' ? 'border-forest-600 text-forest-700' : 'border-transparent text-slate'" class="border-b-2 px-4 py-2 font-body text-[14px] font-500 transition-colors hover:text-forest-700">Shortlist</button>
    </div>

    <div x-show="tab === 'queue'" class="mt-6">
        <p class="font-body text-[13px] text-slate">Applicants still going through screening &mdash; requirements review. Endorsed applicants move to the Shortlist tab.</p>

        <div class="mt-3 flex flex-wrap gap-3">
            <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by name, student ID, or reference no." />
            <x-ui.select name="college_id" wire:model.live="college_id" placeholder="Any college">
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <div class="mt-4">
            @if ($queue->isEmpty())
                <x-ui.empty-state title="No applicants awaiting screening" message="Try a different filter, or check the Shortlist tab for endorsed applicants." />
            @else
                @if (! empty($selected))
                    <div class="mb-3 flex flex-wrap items-center gap-3 rounded-control border border-line bg-paper px-4 py-2">
                        <span class="font-body text-[13px] text-slate">{{ count($selected) }} selected</span>
                        <button type="button" wire:click="bulkVerify" wire:confirm="Mark all pending documents on the selected applications as verified?" class="font-body text-[13px] font-500 text-forest-700 hover:underline">Bulk verify documents</button>
                        <button type="button" wire:click="bulkNotifyIncomplete" class="font-body text-[13px] font-500 text-forest-700 hover:underline">Notify incomplete applicants</button>
                    </div>
                @endif

                <x-ui.data-table :headers="[
                    ['key' => 'select', 'label' => '', 'sortable' => false],
                    ['key' => 'applicant', 'label' => 'Applicant', 'sortable' => false],
                    ['key' => 'college', 'label' => 'College', 'sortable' => false],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]" :rows="$queue">
                    @php $severity = ['crimson-600' => 3, 'amber-600' => 2, 'mist' => 1]; @endphp
                    @foreach ($queue as $application)
                        @php
                            $others = $application->applicant->applications->where('id', '!=', $application->id);
                            $topBadge = $others->map(fn ($other) => $other->outcomeBadge())->sortByDesc(fn ($badge) => $severity[$badge['color']])->first();
                        @endphp
                        <tr wire:key="queue-{{ $application->id }}" class="transition hover:bg-paper">
                            <td class="text-[14px]"><input type="checkbox" wire:model.live="selected" value="{{ $application->id }}"></td>
                            <td data-label="Applicant" class="text-[14px] text-ink">
                                {{ $application->applicant->last_name }}, {{ $application->applicant->first_name }}
                                @if ($topBadge)
                                    <span class="ml-1 border-l-2 border-{{ $topBadge['color'] }} pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-{{ $topBadge['color'] }}" title="Other application: {{ $topBadge['label'] }}">{{ $others->count() }} other</span>
                                @endif
                                <br><span class="font-mono text-[12px] text-slate">{{ $application->reference_no }}</span>
                            </td>
                            <td data-label="College" class="text-[14px] text-slate">{{ $application->college->code }}</td>
                            <td data-label="Status" class="text-[14px]"><x-ui.status-stamp :status="$application->status" /></td>
                            <td data-label="Actions" class="text-[14px]">
                                <a href="{{ route('staff.applications.review', $application) }}" class="font-body font-500 text-forest-700 hover:underline">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </div>
    </div>

    <div x-show="tab === 'shortlist'" class="mt-6" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-body text-[14px] text-slate">Ordered by submission date &mdash; oldest first. Award directly from this list within the slots above.</p>
            <div class="flex shrink-0 gap-3">
                <x-ui.button variant="secondary" wire:click="openRequirements">Set additional requirements</x-ui.button>
                <a href="{{ route('staff.applications.workspace.shortlist.export', $offering) }}" class="inline-flex items-center justify-center rounded-control border border-line bg-surface px-4 py-2 font-body text-[14px] font-500 text-ink hover:border-forest-600">Export shortlist</a>
            </div>
        </div>

        @if ($additionalRequirements->isNotEmpty())
            <div class="mt-4 rounded-card border border-line bg-surface p-4">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Required of every shortlisted applicant</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($additionalRequirements as $requirement)
                        @php $receivedCount = $shortlist->filter(fn ($row) => $row['received']->contains($requirement->id))->count(); @endphp
                        <li wire:key="offering-req-{{ $requirement->id }}" class="flex flex-wrap items-baseline justify-between gap-3 font-body text-[14px] text-ink">
                            <span>{{ $requirement->label }} <span class="text-slate">&middot; {{ $requirement->submission_mode->label() }}</span></span>
                            <span class="flex shrink-0 items-baseline gap-3">
                                <span class="font-mono text-[13px] text-slate">{{ $receivedCount }} / {{ $shortlist->count() }} received</span>
                                <button
                                    type="button"
                                    wire:click="removeRequirement({{ $requirement->id }})"
                                    wire:confirm="Remove &quot;{{ $requirement->label }}&quot;? Every applicant's tick for it is dropped too."
                                    class="font-body text-[13px] text-mist hover:text-crimson-600 hover:underline"
                                >Remove</button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-3">
            <x-ui.select name="shortlist_college_id" wire:model.live="shortlist_college_id" placeholder="Any college">
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </x-ui.select>
        </div>

        @error('slots')<x-ui.alert type="danger" class="mt-3">{{ $message }}</x-ui.alert>@enderror

        <div class="mt-4">
            @if ($shortlist->isEmpty())
                <x-ui.empty-state title="Nothing shortlisted yet" message="Endorse applications in the Queue tab to populate this list." />
            @else
                <x-ui.data-table :headers="[
                    ['key' => 'applicant', 'label' => 'Applicant', 'sortable' => false],
                    ['key' => 'college', 'label' => 'College', 'sortable' => false],
                    ['key' => 'submitted', 'label' => 'Submitted', 'sortable' => false],
                    ['key' => 'requirements', 'label' => 'Additional requirements', 'sortable' => false],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]" :rows="$shortlist">
                    @foreach ($shortlist as $row)
                        @php
                            $application = $row['application'];
                            $outstandingCount = $additionalRequirements->count() - $row['received']->count();
                        @endphp
                        <tr wire:key="shortlist-{{ $application->id }}" class="transition hover:bg-paper">
                            <td data-label="Applicant" class="text-[14px] text-ink">
                                {{ $application->applicant->last_name }}, {{ $application->applicant->first_name }}
                                @if ($row['conflict'])
                                    <span class="ml-1 border-l-2 border-crimson-600 pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-crimson-600" title="Holds another active award">Active award elsewhere</span>
                                @endif
                            </td>
                            <td data-label="College" class="text-[14px] text-slate">{{ $application->college->code }}</td>
                            <td data-label="Submitted" class="font-mono text-[14px] text-slate">{{ $application->submitted_at?->format('M j, Y') }}</td>
                            <td data-label="Additional requirements" class="text-[14px]">
                                @if ($additionalRequirements->isEmpty())
                                    <span class="font-body text-[13px] text-mist">None set</span>
                                @else
                                    <ul class="space-y-1">
                                        @foreach ($additionalRequirements as $requirement)
                                            @php
                                                $receipt = $application->additionalRequirementReceipts->firstWhere('offering_additional_requirement_id', $requirement->id);
                                                $awaitingUpload = ! $receipt && $requirement->submission_mode->isUpload();
                                            @endphp
                                            <li wire:key="receipt-{{ $application->id }}-{{ $requirement->id }}">
                                                <label class="flex items-start gap-1.5 font-body text-[13px] {{ $receipt ? 'text-slate' : 'text-amber-600' }}">
                                                    <input
                                                        type="checkbox"
                                                        class="mt-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                                        wire:click="toggleRequirementReceived({{ $application->id }}, {{ $requirement->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="toggleRequirementReceived({{ $application->id }}, {{ $requirement->id }})"
                                                        @checked($receipt !== null)
                                                        @disabled($awaitingUpload)
                                                        @if ($awaitingUpload) title="The applicant has not uploaded this yet" @endif
                                                    >
                                                    <span>{{ $requirement->label }}</span>
                                                </label>
                                                @if ($awaitingUpload)
                                                    <span class="ml-5 font-body text-[12px] text-mist">Awaiting upload</span>
                                                @elseif ($receipt?->file_path)
                                                    <a href="{{ route('staff.applications.requirements.show', $receipt) }}" target="_blank" rel="noopener" class="ml-5 font-body text-[12px] font-500 text-forest-700 hover:underline">View upload</a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-1 font-body text-[11px] font-600 uppercase tracking-[0.08em] {{ $outstandingCount === 0 ? 'text-forest-700' : 'text-amber-600' }}">
                                        {{ $row['received']->count() }} of {{ $additionalRequirements->count() }} received
                                    </p>
                                @endif
                            </td>
                            <td data-label="Status" class="text-[14px]"><x-ui.status-stamp :status="$application->status" /></td>
                            <td data-label="Actions" class="text-[14px]">
                                @if ($awardingApplicationId === $application->id)
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="confirmAward" wire:loading.attr="disabled" wire:target="confirmAward" class="font-body font-500 text-forest-700 hover:underline">Confirm</button>
                                        <button type="button" wire:click="$set('awardingApplicationId', null)" class="font-body text-slate hover:underline">Cancel</button>
                                    </div>
                                @elseif ($outstandingCount === 0)
                                    <button type="button" wire:click="startAward({{ $application->id }})" class="font-body font-500 text-forest-700 hover:underline">Award</button>
                                @else
                                    <span class="font-body text-[13px] text-mist" title="Mark every additional requirement received first">Award</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </div>
    </div>

    <x-ui.modal :show="$showRequirementsModal" title="Additional requirements">
        @if ($additionalRequirements->isNotEmpty())
            <ul class="space-y-1 border-b border-line pb-4">
                @foreach ($additionalRequirements as $requirement)
                    <li class="flex items-baseline justify-between gap-3 font-body text-[14px] text-ink">
                        <span>{{ $requirement->label }}</span>
                        <span class="shrink-0 font-body text-[13px] text-slate">{{ $requirement->submission_mode->label() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="{{ $additionalRequirements->isNotEmpty() ? 'mt-4' : '' }} space-y-4">
            @foreach ($requirementLines as $index => $line)
                <div wire:key="requirement-line-{{ $index }}" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-48 flex-1">
                        <x-ui.input
                            :name="'requirementLines.'.$index.'.label'"
                            :label="'Requirement '.($index + 1)"
                            wire:model="requirementLines.{{ $index }}.label"
                            placeholder="Notarized affidavit of indigency"
                        />
                    </div>
                    <div class="w-44">
                        <x-ui.select
                            :name="'requirementLines.'.$index.'.submission_mode'"
                            label="How it comes in"
                            wire:model="requirementLines.{{ $index }}.submission_mode"
                        >
                            @foreach (RequirementSubmission::cases() as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    @if (count($requirementLines) > 1)
                        <button type="button" wire:click="removeRequirementLine({{ $index }})" class="pb-2 font-body text-[13px] text-mist hover:text-crimson-600 hover:underline">Remove</button>
                    @endif
                </div>
            @endforeach
        </div>

        @error('requirementLines')
            <p class="mt-2 font-body text-[13px] text-crimson-600">{{ $message }}</p>
        @enderror

        <button type="button" wire:click="addRequirementLine" class="mt-3 font-body text-[13px] font-500 text-forest-700 hover:underline">Add another requirement</button>

        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button variant="secondary" wire:click="closeRequirements">Cancel</x-ui.button>
            <x-ui.button wire:click="saveRequirements" wire:loading.attr="disabled" wire:target="saveRequirements">
                <span wire:loading.remove wire:target="saveRequirements">Save requirements</span>
                <span wire:loading wire:target="saveRequirements">Saving…</span>
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>
