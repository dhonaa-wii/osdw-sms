<?php

use App\Enums\DocumentStatus;
use App\Enums\RequirementStage;
use App\Models\Award;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use App\Services\AwardService;
use App\Services\OfferingRequirementService;
use App\Services\OfferingService;
use App\Services\RenewalService;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Grantees'))] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'college')]
    public string $college_id = '';

    #[Url(as: 'program')]
    public string $scholarship_program_id = '';

    /** The first offering of the chosen offering group. */
    #[Url(as: 'group')]
    public string $group_id = '';

    /** The offering within that group; blank means its latest one. */
    #[Url(as: 'offering')]
    public string $offering_id = '';

    /**
     * The award whose disbursement requirements are open in the dialog. The
     * dialog reads it through $wire, so it opens on click and stays open
     * while verify and return re-render the page.
     */
    public ?int $disbursementAwardId = null;

    public bool $showPublishModal = false;

    public string $instructions = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCollegeId(): void
    {
        $this->resetPage();
    }

    public function updatedScholarshipProgramId(): void
    {
        $this->group_id = '';
        $this->offering_id = '';
        $this->resetPage();
    }

    public function updatedGroupId(): void
    {
        $this->offering_id = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'college_id', 'scholarship_program_id', 'group_id', 'offering_id');
        $this->resetPage();
    }

    public function publish(OfferingService $offeringService): void
    {
        $this->validate(
            ['instructions' => ['required', 'string', 'max:5000']],
            ['instructions.required' => 'Tell awardees what to do next, e.g. where and when to report.'],
        );

        try {
            $offeringService->publishResults($this->actionOffering(), trim($this->instructions));
        } catch (\RuntimeException $exception) {
            $this->addError('instructions', $exception->getMessage());

            return;
        }

        $this->showPublishModal = false;
        $this->resetValidation();
        $this->dispatch('notice', title: 'Results published', message: 'The awardee list is on the public results page.');
    }

    public function unpublish(OfferingService $offeringService): void
    {
        $offeringService->unpublishResults($this->actionOffering());
        $this->dispatch('notice', title: 'Results unpublished', message: 'The awardee list is off the public site.');
    }

    public function openAwardRequirements(OfferingService $offeringService): void
    {
        try {
            $offeringService->openAwardRequirements($this->actionOffering());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t open requirements', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->dispatch('notice', title: 'Disbursement requirements opened', message: 'Awardees can now submit them from their tracking page.');
    }

    public function disburseAll(AwardService $awardService): void
    {
        try {
            $count = $awardService->disburseAll($this->actionOffering(), auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t disburse', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->dispatch('notice', title: 'Grantees disbursed', message: $count.' '.str('grantee')->plural($count).' marked disbursed.');
    }

    /**
     * The offering the page is filtered to, which the publish and open
     * actions apply to. Only an offering that takes new applicants has
     * slots to fill.
     */
    protected function actionOffering(): ProgramOffering
    {
        $group = ProgramOffering::whereNull('renews_offering_id')
            ->where('scholarship_program_id', $this->scholarship_program_id)
            ->findOrFail($this->group_id);

        $offering = $this->selectedOffering(app(RenewalService::class)->chainOf($group));

        abort_unless($offering?->takesNewApplicants(), 404);

        return $offering;
    }

    public function openDisbursement(int $awardId): void
    {
        $this->disbursementAwardId = Award::findOrFail($awardId)->id;
        $this->resetValidation();
    }

    public function verifyRequirement(int $submissionId, OfferingRequirementService $service): void
    {
        $service->verify($this->disbursementSubmission($submissionId), auth()->user());
    }

    public function rejectRequirement(int $submissionId, string $remarks, OfferingRequirementService $service): bool
    {
        if (trim($remarks) === '') {
            $this->dispatch('notice', title: 'Reason needed', message: 'Tell the scholar what to fix before returning the upload.', tone: 'error');

            return false;
        }

        $service->reject($this->disbursementSubmission($submissionId), trim($remarks), auth()->user());
        $this->dispatch('notice', title: 'Upload returned', message: 'The scholar can upload a corrected file from their tracking page.');

        return true;
    }

    public function togglePhysicalRequirement(int $requirementId, OfferingRequirementService $service): void
    {
        $application = $this->disbursementAward()->application;

        $service->togglePhysicalReceived(
            $application,
            $application->programOffering->additionalRequirements()->where('stage', RequirementStage::Award->value)->findOrFail($requirementId),
            auth()->user(),
        );
    }

    protected function disbursementAward(): Award
    {
        return Award::with('application.programOffering', 'applicant')->findOrFail($this->disbursementAwardId);
    }

    /**
     * Only the open award's disbursement-stage submissions can be verified
     * or returned from here.
     */
    protected function disbursementSubmission(int $submissionId)
    {
        return $this->disbursementAward()->application->additionalRequirementSubmissions()
            ->whereHas('requirement', fn ($query) => $query->where('stage', RequirementStage::Award->value))
            ->findOrFail($submissionId);
    }

    /**
     * The offering whose scholars are listed when a group is chosen: the one
     * picked from its tabs, or the latest in the chain.
     */
    protected function selectedOffering(Collection $chain): ?ProgramOffering
    {
        return $chain->firstWhere('id', (int) $this->offering_id) ?? $chain->last();
    }

    public function with(RenewalService $renewalService, ReportService $reportService, OfferingRequirementService $requirementService): array
    {
        $groups = $this->scholarship_program_id === '' ? collect() : ProgramOffering::query()
            ->where('scholarship_program_id', $this->scholarship_program_id)
            ->whereNull('renews_offering_id')
            ->with('academicTerm')
            ->get()
            ->sortByDesc(fn (ProgramOffering $offering) => $offering->academicTerm->start_date)
            ->values();

        $group = $groups->firstWhere('id', (int) $this->group_id);
        $chain = $group ? $renewalService->chainOf($group) : collect();
        $selectedOffering = $this->selectedOffering($chain);

        $awards = $reportService
            ->granteeQuery(
                $this->search,
                $this->scholarship_program_id !== '' ? (int) $this->scholarship_program_id : null,
                $selectedOffering,
                $this->college_id !== '' ? (int) $this->college_id : null,
            )
            ->with([
                'applicant', 'application.college', 'application.additionalRequirementSubmissions.requirement',
                'programOffering.scholarshipProgram.provider', 'programOffering.academicTerm',
                'programOffering.additionalRequirements' => fn ($query) => $query->where('stage', RequirementStage::Award->value),
            ])
            ->paginate(25);

        // Disbursement progress per row: complied out of the offering's
        // award-stage requirements, and uploads still waiting for staff.
        $disbursement = $awards->getCollection()->mapWithKeys(function (Award $award) {
            $submissions = $award->application->additionalRequirementSubmissions
                ->filter(fn ($submission) => $submission->requirement->stage === RequirementStage::Award);

            // A renewal answers renewal requirements, not disbursement ones.
            return [$award->id => [
                'total' => $award->application->isRenewal() ? 0 : $award->programOffering->additionalRequirements->count(),
                'done' => $submissions->filter(fn ($submission) => $submission->isComplied())->count(),
                'toReview' => $submissions->filter(fn ($submission) => $submission->file_path && $submission->status === DocumentStatus::Pending)->count(),
            ]];
        });

        $disbursementAward = $this->disbursementAwardId ? $this->disbursementAward() : null;
        $awardService = app(AwardService::class);
        $remainingSlots = $selectedOffering?->takesNewApplicants()
            ? $awardService->remainingSlots($selectedOffering)
            : null;

        return [
            'awards' => $awards,
            'disbursement' => $disbursement,
            'colleges' => College::orderBy('name')->get(),
            'scholarshipPrograms' => ScholarshipProgram::orderBy('name')->get(),
            'groups' => $groups,
            'chain' => $chain,
            'selectedOffering' => $selectedOffering,
            'remainingSlots' => $remainingSlots,
            'totalSlots' => $remainingSlots !== null ? (int) $selectedOffering->total_slots : null,
            'allDisbursed' => $remainingSlots === 0 && ! Award::where('program_offering_id', $selectedOffering->id)->whereNull('disbursed_at')->exists(),
            'disburseAllBlocker' => $remainingSlots !== null ? $awardService->disburseAllBlocker($selectedOffering) : null,
            'filtered' => trim($this->search) !== '' || $this->college_id !== '' || $this->scholarship_program_id !== '',
            'disbursementAward' => $disbursementAward,
            'disbursementChecklist' => $disbursementAward
                ? $requirementService->checklist($disbursementAward->application, RequirementStage::Award)
                : collect(),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Grantees</h1>
        @if ($awards->total() > 0)
            <p class="flex items-center gap-1.5 font-body text-[13px] text-slate">
                <x-ui.icon name="award" class="size-4 text-mist" />
                <span class="font-mono text-ink">{{ $awards->total() }}</span> {{ str('grantee')->plural($awards->total()) }}
            </p>
        @endif
    </div>

    @if ($selectedOffering)
        <x-staff.offering-chain-tabs
            class="mt-4 border-b border-line"
            :chain="$chain"
            :current="$selectedOffering"
            :manage="false"
            :href-for="fn ($member) => route('staff.grantees.index', array_filter(['q' => $search, 'college' => $college_id, 'program' => $scholarship_program_id, 'group' => $group_id, 'offering' => $member->id]))"
        />

        @if ($remainingSlots !== null)
            @php
                $awardRequirementsOpened = $selectedOffering->hasOpenedRequirements(RequirementStage::Award);
                $awardedSlots = max(0, $totalSlots - $remainingSlots);
                $slotsFull = $remainingSlots === 0;
            @endphp

            <div class="mt-4 flex flex-col gap-3 rounded-card border border-line bg-surface px-4 py-3 md:flex-row md:items-center">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-700">
                        <x-ui.icon name="award" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p class="font-body text-[13px] text-ink">
                                <span class="font-mono">{{ $awardedSlots }}/{{ $totalSlots }}</span> slots awarded
                                @unless ($slotsFull)
                                    <span class="text-amber-600">&middot; <span class="font-mono">{{ $remainingSlots }}</span> open</span>
                                @endunless
                            </p>
                            @if ($selectedOffering->results_published)
                                <span class="border-l-2 border-navy-600 pl-2 font-body text-[10px] font-600 uppercase tracking-[0.08em] text-navy-700" title="Published {{ $selectedOffering->results_published_at?->format('M j, Y') }}">Published</span>
                            @endif
                            @if ($awardRequirementsOpened)
                                <span class="border-l-2 border-navy-600 pl-2 font-body text-[10px] font-600 uppercase tracking-[0.08em] text-navy-700" title="Opened {{ $selectedOffering->award_requirements_opened_at->format('M j, Y') }}">Disbursement open</span>
                            @endif
                            @if ($allDisbursed)
                                <span class="border-l-2 border-gold-600 pl-2 font-body text-[10px] font-600 uppercase tracking-[0.08em] text-gold-600">Disbursed</span>
                            @endif
                        </div>
                        <div class="mt-1.5 h-1 w-full max-w-72 overflow-hidden rounded-full bg-paper" role="progressbar" aria-label="Slots awarded" aria-valuemin="0" aria-valuemax="{{ $totalSlots }}" aria-valuenow="{{ $awardedSlots }}">
                            <div class="h-full rounded-full bg-navy-600 transition-[width] duration-300" style="width: {{ $totalSlots > 0 ? round($awardedSlots / $totalSlots * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2" @unless ($slotsFull) title="Award every slot first" @endunless>
                    @if ($selectedOffering->results_published)
                        <x-ui.button
                            variant="ghost"
                            class="px-3 py-1.5 text-[13px]"
                            x-on:click="$wire.instructions = {{ \Illuminate\Support\Js::from($selectedOffering->publish_instructions ?? '') }}; $wire.showPublishModal = true"
                        >
                            <x-ui.icon name="pencil" class="size-3.5" />
                            Edit instructions
                        </x-ui.button>
                        <x-ui.button
                            variant="secondary"
                            class="px-3 py-1.5 text-[13px]"
                            loading="unpublish"
                            x-on:click="$store.confirm.ask({ title: 'Unpublish results?', message: 'The awardee list comes off the public site.', confirmLabel: 'Unpublish results', action: () => $wire.unpublish() })"
                        >
                            <x-ui.icon name="eye" class="size-3.5" />
                            Unpublish results
                        </x-ui.button>
                    @else
                        <x-ui.button
                            variant="secondary"
                            class="px-3 py-1.5 text-[13px]"
                            :disabled="! $slotsFull"
                            x-on:click="$wire.instructions = {{ \Illuminate\Support\Js::from($selectedOffering->publish_instructions ?? '') }}; $wire.showPublishModal = true"
                        >
                            <x-ui.icon name="megaphone" class="size-3.5" />
                            Publish results
                        </x-ui.button>
                    @endif
                    @unless ($awardRequirementsOpened)
                        <x-ui.button
                            class="px-3 py-1.5 text-[13px]"
                            loading="openAwardRequirements"
                            :disabled="! $slotsFull"
                            x-on:click="$store.confirm.ask({
                                title: 'Open disbursement requirements?',
                                message: 'Every awardee will see them on their tracking page. This can’t be undone.',
                                confirmLabel: 'Open requirements',
                                tone: 'primary',
                                action: () => $wire.openAwardRequirements(),
                            })"
                        >
                            <x-ui.icon name="clipboard-check" class="size-3.5" />
                            Open disbursement requirements
                        </x-ui.button>
                    @endunless
                    @unless ($allDisbursed)
                        <span @if ($disburseAllBlocker) title="{{ $disburseAllBlocker }}" @endif>
                            <x-ui.button
                                class="px-3 py-1.5 text-[13px]"
                                loading="disburseAll"
                                :disabled="$disburseAllBlocker !== null"
                                x-on:click="$store.confirm.ask({
                                    title: 'Disburse all grantees?',
                                    message: 'Every grantee in this offering is recorded as disbursed. This can’t be undone.',
                                    confirmLabel: 'Disburse all',
                                    tone: 'primary',
                                    action: () => $wire.disburseAll(),
                                })"
                            >
                                <x-ui.icon name="award" class="size-3.5" />
                                Disburse all
                            </x-ui.button>
                        </span>
                    @endunless
                </div>
            </div>
        @endif
    @endif

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-2 border-b border-line p-3 md:flex-row md:flex-wrap md:items-center">
            <x-ui.search
                name="search"
                label="Search grantees"
                placeholder="Search by name or student ID"
                wire:model.live.debounce.300ms="search"
                class="md:w-64"
            />

            <x-ui.filter-select name="scholarship_program_id" label="Scholarship program" icon="award" wire:model.live="scholarship_program_id" class="md:w-52">
                <option value="">All programs</option>
                @foreach ($scholarshipPrograms as $program)
                    <option value="{{ $program->id }}">{{ $program->name }}</option>
                @endforeach
            </x-ui.filter-select>

            @if ($scholarship_program_id !== '')
                <x-ui.filter-select name="group_id" label="Offering" icon="layers" wire:model.live="group_id" class="md:w-56" wire:key="group-filter-{{ $scholarship_program_id }}">
                    <option value="">All offerings</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->displayName() }} &middot; {{ $group->academicTerm->school_year }} {{ $group->academicTerm->semester->label() }}</option>
                    @endforeach
                </x-ui.filter-select>
            @endif

            <x-ui.filter-select name="college_id" label="College" icon="building" wire:model.live="college_id" class="md:w-48">
                <option value="">All colleges</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </x-ui.filter-select>

            @if ($filtered)
                <x-ui.button variant="ghost" class="px-2.5 py-1.5 text-[13px] md:ml-auto" loading="clearFilters" wire:click="clearFilters">
                    <x-ui.icon name="x" class="size-3.5" />
                    Clear filters
                </x-ui.button>
            @endif
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="search, college_id, scholarship_program_id, group_id, clearFilters, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($awards->isEmpty())
                @if ($filtered)
                    <x-ui.list-empty icon="search" title="No grantees match" message="Try another name or student ID, or clear the filters.">
                        <x-ui.button variant="secondary" wire:click="clearFilters">Clear filters</x-ui.button>
                    </x-ui.list-empty>
                @else
                    <x-ui.list-empty icon="award" title="No grantees recorded yet" message="Award applicants from an offering's Shortlist tab to record them here.">
                        <x-ui.button-link :href="route('staff.applications.index')" wire:navigate>Go to applications</x-ui.button-link>
                    </x-ui.list-empty>
                @endif
            @else
                <x-ui.list-table>
                    <x-slot:head>
                        <th>Grantee</th>
                        <th class="md:w-px md:whitespace-nowrap">College</th>
                        <th class="md:w-px md:whitespace-nowrap">Year</th>
                        <th>Scholarship</th>
                        <th class="md:w-px md:whitespace-nowrap md:text-right">Amount</th>
                        <th class="md:w-px md:whitespace-nowrap">Disbursement</th>
                        <th class="md:w-px"><span class="sr-only">Actions</span></th>
                    </x-slot:head>

                    @foreach ($awards as $award)
                        @php
                            $applicant = $award->applicant;
                            $offering = $award->programOffering;
                            $viewUrl = route('staff.grantees.show', $award);
                            $progress = $disbursement[$award->id];
                            $allComplied = $progress['done'] === $progress['total'];
                        @endphp
                        <tr
                            wire:key="grantee-{{ $award->id }}"
                            class="group cursor-pointer transition-colors hover:bg-paper/60"
                            x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $viewUrl }}')"
                        >
                            <td class="col-start-1 min-w-0">
                                <a href="{{ $viewUrl }}" wire:navigate x-on:click.stop class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                    {{ $applicant->last_name }}, {{ $applicant->first_name }}{{ $applicant->middle_name ? ' '.mb_substr($applicant->middle_name, 0, 1).'.' : '' }}
                                </a>
                                <p class="flex items-center gap-2 font-mono text-[12px] text-mist">
                                    {{ $applicant->student_id }}
                                    @if ($award->application->isRenewal())
                                        <span class="border-l-2 border-navy-600 pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-navy-700">Renewed</span>
                                    @endif
                                </p>
                            </td>
                            <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap" title="{{ $award->application->college->name }}"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="building" class="size-3.5 text-mist" />{{ $award->application->college->code }}</span></td>
                            <td class="col-start-1 font-body text-[13px]">
                                <span class="text-mist md:hidden">Year</span>
                                <span class="font-mono text-ink">{{ $award->application->year_level ?? '—' }}</span>
                            </td>
                            <td class="col-start-1 min-w-0">
                                <p class="truncate font-body text-[13px] text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</p>
                                <p class="truncate font-body text-[12px] text-mist">{{ $offering->category->label() }} &middot; {{ $offering->scholarshipProgram->provider->funding_source->label() }}</p>
                            </td>
                            <td class="col-start-1 font-mono text-[13px] text-ink md:whitespace-nowrap md:text-right">{{ $award->amount ? '₱'.number_format($award->amount, 2) : '—' }}</td>
                            <td class="col-start-1 font-body text-[13px] md:whitespace-nowrap" x-on:click.stop>
                                @if ($award->isDisbursed())
                                    <span class="border-l-2 border-gold-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-gold-600" title="Disbursed {{ $award->disbursed_at->format('M j, Y') }}">Disbursed</span>
                                @elseif ($progress['total'] === 0)
                                    <span class="text-mist">None</span>
                                @else
                                    <button
                                        type="button"
                                        x-on:click="$wire.disbursementAwardId = {{ $award->id }}; $wire.openDisbursement({{ $award->id }})"
                                        aria-label="Disbursement requirements of {{ $applicant->first_name }} {{ $applicant->last_name }}"
                                        class="inline-flex items-center gap-1.5 rounded-control px-1.5 py-1 -mx-1.5 transition-colors hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 {{ $allComplied ? 'text-navy-700' : 'text-amber-600' }}"
                                    >
                                        <x-ui.icon :name="$allComplied ? 'check-circle' : 'clock'" class="size-3.5" />
                                        <span class="font-mono">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
                                        @if ($progress['toReview'] > 0)
                                            <span class="text-[12px] text-azure-600">&middot; <span class="font-mono">{{ $progress['toReview'] }}</span> to review</span>
                                        @endif
                                    </button>
                                @endif
                            </td>
                            <td class="col-start-2 row-span-6 row-start-1 self-center" x-on:click.stop>
                                <div class="flex justify-end">
                                    <x-ui.icon-button icon="chevron-right" label="View {{ $applicant->first_name }} {{ $applicant->last_name }}" :href="$viewUrl" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.list-table>

                @if ($awards->hasPages())
                    <div class="border-t border-line px-4 py-2.5">
                        {{ $awards->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    <x-ui.dialog show="$wire.disbursementAwardId !== null" close="$wire.disbursementAwardId = null" title="Disbursement requirements" size="2xl" loading="openDisbursement">
        <div class="min-h-32">
            @if ($disbursementAward)
                <div wire:loading.remove wire:target="openDisbursement">
                    <div class="mb-3 flex items-center gap-3 rounded-control bg-paper px-3 py-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-navy-50 font-display text-[12px] font-600 text-navy-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($disbursementAward->applicant->first_name, 0, 1).mb_substr($disbursementAward->applicant->last_name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-body text-[14px] font-500 text-ink">{{ $disbursementAward->applicant->last_name }}, {{ $disbursementAward->applicant->first_name }}</p>
                            <p class="truncate font-body text-[12px] text-mist"><span class="font-mono">{{ $disbursementAward->applicant->student_id }}</span> &middot; {{ $disbursementAward->application->programOffering->displayName() }}</p>
                        </div>
                        <a href="{{ route('staff.grantees.show', $disbursementAward) }}" wire:navigate class="inline-flex shrink-0 items-center gap-1 rounded-control px-2 py-1 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                            View grantee
                            <x-ui.icon name="arrow-right" class="size-3.5" />
                        </a>
                    </div>

                    <x-staff.requirement-checklist :stage="RequirementStage::Award" :rows="$disbursementChecklist" bare />
                </div>
            @endif
        </div>
    </x-ui.dialog>

    @if ($remainingSlots !== null)
        <x-ui.dialog show="$wire.showPublishModal" close="$wire.showPublishModal = false" :title="$selectedOffering->results_published ? 'Edit instructions' : 'Publish results'">
            <form wire:submit="publish" class="space-y-4">
                <x-ui.textarea name="instructions" label="Instructions for awardees" wire:model="instructions" rows="5" required hint="Shown above the awardee list on the public results page." />

                <div class="flex justify-end gap-2">
                    <x-ui.button variant="secondary" class="px-3 py-1.5 text-[13px]" x-on:click="$wire.showPublishModal = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" class="px-3 py-1.5 text-[13px]" loading="publish">{{ $selectedOffering->results_published ? 'Save instructions' : 'Publish results' }}</x-ui.button>
                </div>
            </form>
        </x-ui.dialog>
    @endif
</div>
