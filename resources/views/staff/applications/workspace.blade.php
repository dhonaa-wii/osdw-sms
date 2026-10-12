<?php

use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\EmailType;
use App\Enums\RequirementStage;
use App\Livewire\Concerns\DeletesOfferings;
use App\Mail\IncompleteApplicationMail;
use App\Models\Application;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Services\AwardService;
use App\Services\EmailService;
use App\Services\OfferingRequirementService;
use App\Services\OfferingService;
use App\Services\RenewalService;
use App\Services\ScholarshipHoldingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Applications'))] class extends Component
{
    use DeletesOfferings;

    public ProgramOffering $offering;

    public string $search = '';

    public string $college_id = '';

    public array $selected = [];

    protected array $awardedStatuses = ['awarded'];

    /**
     * Statuses that are still mid-screening: requirements review only. Once
     * an applicant is endorsed or rejected they move to `shortlisted` or
     * `denied` and leave this queue for good.
     */
    protected array $screeningStatuses = ['submitted', 'screening'];

    public function mount(ProgramOffering $offering): void
    {
        // A renewal offering has a queue and shortlist only when it takes
        // new applicants; otherwise it holds renewals alone.
        if (! $offering->takesNewApplicants()) {
            throw new HttpResponseException(new RedirectResponse(route('staff.renewals.index', $offering)));
        }

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
        $this->dispatch(
            'notice',
            title: $count > 0 ? 'Documents verified' : 'Nothing to verify',
            message: $count > 0
                ? "{$count} pending ".str('document')->plural($count).' marked verified.'
                : 'The selected applications have no pending documents.',
        );
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
        $this->dispatch(
            'notice',
            title: $notified > 0 ? 'Applicants notified' : 'No one to notify',
            message: $notified > 0
                ? "Emailed {$notified} ".str('applicant')->plural($notified).' about a rejected document.'
                : 'None of the selected applications have a rejected document.',
        );
    }

    public function award(int $applicationId): void
    {
        $application = $this->offering->applications()->with('applicant')->findOrFail($applicationId);
        $service = app(AwardService::class);
        $collegeId = $this->offering->allocation_mode->value === 'quota' ? $application->college_id : null;

        if ($service->remainingSlots($this->offering, $collegeId) <= 0) {
            $this->dispatch('notice', title: 'No slots remaining', message: 'Every slot for this offering is already awarded.', tone: 'error');

            return;
        }

        if (app(OfferingRequirementService::class)->outstandingCount($application, RequirementStage::Shortlist) > 0) {
            $this->dispatch('notice', title: 'Requirements outstanding', message: 'Verify this applicant\'s shortlist requirements on their review page before awarding.', tone: 'error');

            return;
        }

        try {
            $service->create($application, auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t award', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->dispatch('notice', title: 'Award recorded', message: "{$application->applicant->first_name} {$application->applicant->last_name} has been emailed the award notice.");
    }

    public function openShortlistRequirements(OfferingService $offeringService): void
    {
        try {
            $offeringService->openShortlistRequirements($this->offering);
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', title: 'Couldn\'t open requirements', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->offering->refresh();
        $this->dispatch('notice', title: 'Shortlist requirements opened', message: 'Shortlisted applicants can now submit them from their tracking page.');
    }

    /**
     * Per applicant: how many scholarships they hold in other programs, and
     * whether one is a national scholarship that rules out this (national)
     * offering.
     *
     * @return \Illuminate\Support\Collection<int, array{held: int, national: bool}>
     */
    protected function holdingFlags($applicantIds)
    {
        $holdings = app(ScholarshipHoldingService::class);
        $offeringIsNational = $holdings->isNational($this->offering);
        $held = $holdings->heldAwardsAmong($applicantIds, $this->offering);

        return $applicantIds->mapWithKeys(fn ($id) => [$id => [
            'held' => $held->get($id, collect())->count(),
            'national' => $offeringIsNational && $held->get($id, collect())->contains(fn ($award) => $holdings->isNational($award->programOffering)),
        ]]);
    }

    public function with(): array
    {
        $matchesSearch = fn ($query) => $query->whereHas('applicant', fn ($query) => $query->where(fn ($query) => $query
            ->where('last_name', 'like', "%{$this->search}%")
            ->orWhere('first_name', 'like', "%{$this->search}%")
            ->orWhere('student_id', 'like', "%{$this->search}%")));

        $queue = $this->offering->applications()
            ->where('kind', ApplicationKind::New->value)
            ->whereIn('status', $this->screeningStatuses)
            ->withCount(['screenings as failed_screenings_count' => fn ($query) => $query->where('passed', false)])
            ->with(['applicant.applications.award', 'college'])
            ->when($this->search, $matchesSearch)
            ->when($this->college_id, fn ($query) => $query->where('college_id', $this->college_id))
            ->orderBy('failed_screenings_count')
            ->orderByDesc('submitted_at')
            ->get();

        $shortlistRequirementCount = $this->offering->additionalRequirements()
            ->where('stage', RequirementStage::Shortlist->value)
            ->count();

        $shortlist = $this->offering->applications()
            ->with(['applicant', 'college', 'additionalRequirementSubmissions.requirement'])
            ->where('status', 'shortlisted')
            ->when($this->search, $matchesSearch)
            ->when($this->college_id, fn ($query) => $query->where('college_id', $this->college_id))
            ->orderBy('college_id')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn ($application) => [
                'application' => $application,
                'complied' => $application->additionalRequirementSubmissions
                    ->filter(fn ($submission) => $submission->requirement->stage === RequirementStage::Shortlist && $submission->isComplied())
                    ->count(),
            ]);

        $slotCounters = collect();

        if ($this->offering->allocation_mode->value === 'quota') {
            foreach ($this->offering->offeringColleges as $offeringCollege) {
                $used = $this->offering->applications()
                    ->where('kind', ApplicationKind::New->value)
                    ->where('college_id', $offeringCollege->college_id)
                    ->whereIn('status', $this->awardedStatuses)
                    ->count();

                $slotCounters->push([
                    'label' => $offeringCollege->college->code ?? $offeringCollege->college->name,
                    'used' => $used,
                    'total' => $offeringCollege->slots,
                ]);
            }
        } else {
            $used = $this->offering->applications()->where('kind', ApplicationKind::New->value)->whereIn('status', $this->awardedStatuses)->count();
            $slotCounters->push(['label' => 'Awarded', 'used' => $used, 'total' => $this->offering->total_slots]);
        }

        return [
            'chain' => app(RenewalService::class)->chainOf($this->offering),
            'renewalsToReview' => $this->offering->isRenewal()
                ? $this->offering->applications()->where('kind', ApplicationKind::Renewal->value)->where('status', ApplicationStatus::Screening->value)->count()
                : 0,
            'queue' => $queue,
            'shortlist' => $shortlist,
            'holdingFlags' => $this->holdingFlags($queue->pluck('applicant_id')->merge($shortlist->pluck('application.applicant_id'))->unique()->values()),
            'slotCounters' => $slotCounters,
            'colleges' => College::whereIn('id', $this->offering->applications()->distinct()->pluck('college_id'))->orderBy('name')->get(),
            'shortlistRequirementCount' => $shortlistRequirementCount,
            'shortlistedCount' => app(OfferingService::class)->shortlistedCount($this->offering),
        ];
    }
};
?>

@php
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $requirementsOpened = $offering->hasOpenedRequirements(RequirementStage::Shortlist);
    $shortlistShort = max(0, $offering->total_slots - $shortlistedCount);
    $filtered = $search !== '' || $college_id !== '';
@endphp

<div
    x-data="{ tab: @js(request('tab') === 'shortlist' ? 'shortlist' : 'queue') }"
    x-init="$watch('tab', value => { const url = new URL(window.location); url.searchParams.set('tab', value); history.replaceState(history.state, '', url); })"
>
    <x-ui.back-link :href="route('staff.applications.index')" label="Applications" wire:navigate />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $chain->first()->displayName() }}</h1>
        <x-ui.button-link variant="secondary" :href="route('staff.grantees.index', ['program' => $offering->scholarship_program_id])" wire:navigate>
            <x-ui.icon name="users" class="size-4" />
            Grantees
        </x-ui.button-link>
    </div>

    @if ($chain->count() > 1)
        <x-staff.offering-chain-tabs :chain="$chain" :current="$offering" class="mt-4 border-b border-line" />
    @endif

    @if ($offering->isRenewal())
        <x-staff.renewal-offering-nav class="mt-4" :offering="$offering" current="new" :new-count="$queue->count()" :renewal-count="$renewalsToReview" />
    @endif

    {{-- Slot usage and pipeline counts --}}
    @php
        $slotsUsed = $slotCounters->sum('used');
        $slotsTotal = $slotCounters->sum('total');
        $slotsPercent = $slotsTotal > 0 ? min(100, round($slotsUsed / $slotsTotal * 100)) : 0;
    @endphp
    <div class="mt-5 flex flex-col overflow-hidden rounded-card border border-line bg-surface md:flex-row">
        <div class="flex-1 px-4 py-3.5">
            <div class="flex items-baseline justify-between gap-3">
                <p class="flex items-center gap-1.5 font-body text-[13px] text-slate">
                    <x-ui.icon name="award" class="size-4 shrink-0 {{ $slotsPercent >= 100 ? 'text-gold-600' : 'text-mist' }}" />
                    Awarded slots
                </p>
                <p class="font-body text-[12px] text-slate">
                    <span class="font-mono text-[20px] leading-none text-ink">{{ $slotsUsed }}</span>
                    <span class="font-mono text-[13px] text-slate">/ {{ $slotsTotal }}</span>
                    <span class="ml-2">{{ max(0, $slotsTotal - $slotsUsed) }} left</span>
                </p>
            </div>
            <div class="mt-2.5 h-1.5 rounded-full bg-paper">
                <div class="h-1.5 rounded-full {{ $slotsPercent >= 100 ? 'bg-gold-600' : 'bg-navy-600' }}" style="width: {{ $slotsPercent }}%"></div>
            </div>

            @if ($slotCounters->count() > 1)
                <div class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($slotCounters as $counter)
                        @php $percent = $counter['total'] > 0 ? min(100, round($counter['used'] / $counter['total'] * 100)) : 0; @endphp
                        <div class="flex items-center gap-2.5">
                            <p class="w-16 shrink-0 truncate font-body text-[12px] text-slate">{{ $counter['label'] }}</p>
                            <div class="h-1 flex-1 rounded-full bg-paper">
                                <div class="h-1 rounded-full {{ $percent >= 100 ? 'bg-gold-600' : 'bg-navy-600' }}" style="width: {{ $percent }}%"></div>
                            </div>
                            <p class="shrink-0 font-mono text-[12px] text-ink">{{ $counter['used'] }}/{{ $counter['total'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 border-t border-line md:w-80 md:border-t-0 md:border-l">
            <div class="px-4 py-3.5">
                <p class="flex items-center gap-1.5 font-body text-[13px] text-slate">
                    <x-ui.icon name="clock" class="size-4 shrink-0 {{ $queue->isNotEmpty() ? 'text-amber-600' : 'text-mist' }}" />
                    In queue
                </p>
                <p class="mt-1.5 font-mono text-[20px] leading-none text-ink">{{ $queue->count() }}</p>
            </div>
            <div class="border-l border-line px-4 py-3.5">
                <p class="flex items-center gap-1.5 font-body text-[13px] text-slate">
                    <x-ui.icon name="shortlist" class="size-4 shrink-0 text-mist" />
                    Shortlisted
                </p>
                <p class="mt-1.5 font-mono text-[20px] leading-none text-ink">{{ $shortlistedCount }}</p>
            </div>
        </div>
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        {{-- Toolbar: one search and college filter for both lists --}}
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search applicants"
                placeholder="Search by name or student ID"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <x-ui.filter-select name="college_id" label="Filter by college" icon="building" wire:model.live="college_id" class="md:w-60">
                <option value="">All colleges</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </x-ui.filter-select>

            <x-ui.segmented
                class="md:ml-auto"
                label="Show list"
                alpine="tab"
                :options="['queue' => 'Queue', 'shortlist' => 'Shortlist']"
                :counts="['queue' => $queue->count(), 'shortlist' => $shortlist->count()]"
                :alert="['queue']"
            />
        </div>

        {{-- Queue --}}
        <div x-show="tab === 'queue'">
            <div
                x-show="$wire.selected.length > 0"
                x-cloak
                class="flex flex-wrap items-center gap-2 border-b border-line bg-navy-50/60 px-4 py-2"
            >
                <span class="font-body text-[13px] text-ink"><span class="font-mono" x-text="$wire.selected.length"></span> selected</span>
                <div class="ml-auto flex flex-wrap items-center gap-1">
                    <x-ui.button
                        variant="ghost"
                        class="px-2.5 py-1 text-[13px]"
                        loading="bulkVerify"
                        x-on:click="$store.confirm.ask({
                            title: 'Verify pending documents?',
                            message: 'Every pending document on the selected applications is marked verified.',
                            confirmLabel: 'Verify documents',
                            tone: 'primary',
                            action: () => $wire.bulkVerify(),
                        })"
                    >
                        <x-ui.icon name="check-square" class="size-4" />
                        Verify documents
                    </x-ui.button>
                    <x-ui.button
                        variant="ghost"
                        class="px-2.5 py-1 text-[13px]"
                        loading="bulkNotifyIncomplete"
                        x-on:click="$store.confirm.ask({
                            title: 'Email incomplete applicants?',
                            message: 'Applicants with a rejected document are emailed what to fix.',
                            confirmLabel: 'Send emails',
                            tone: 'primary',
                            action: () => $wire.bulkNotifyIncomplete(),
                        })"
                    >
                        <x-ui.icon name="mail" class="size-4" />
                        Notify incomplete
                    </x-ui.button>
                    <x-ui.icon-button icon="x" label="Clear selection" x-on:click="$wire.selected = []" />
                </div>
            </div>

            <div wire:loading.delay.class="opacity-60" wire:target="search, college_id, bulkVerify, bulkNotifyIncomplete" class="transition-opacity">
                @if ($queue->isEmpty())
                    @if ($filtered)
                        <x-ui.list-empty icon="search" title="No applicants match" message="Try another name, student ID or college.">
                            <x-ui.button variant="secondary" wire:click="$set('search', ''); $set('college_id', '')">Clear filters</x-ui.button>
                        </x-ui.list-empty>
                    @else
                        <x-ui.list-empty icon="check-circle" title="No applicants awaiting screening" message="Endorsed applicants are on the Shortlist.">
                            <x-ui.button variant="secondary" x-on:click="tab = 'shortlist'">Open shortlist</x-ui.button>
                        </x-ui.list-empty>
                    @endif
                @else
                    @php
                        $severity = ['crimson-600' => 3, 'amber-600' => 2, 'mist' => 1];
                        $queueIds = $queue->pluck('id')->map(fn ($id) => (string) $id)->values();
                    @endphp
                    <x-ui.list-table>
                        <x-slot:head>
                            <th>
                                <span class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        aria-label="Select every applicant in the queue"
                                        class="rounded border-line text-navy-600 focus-visible:ring-2 focus-visible:ring-navy-600"
                                        x-bind:checked="$wire.selected.length === {{ $queueIds->count() }}"
                                        x-on:change="$wire.selected = $event.target.checked ? @js($queueIds) : []"
                                    >
                                    Applicant
                                </span>
                            </th>
                            <th class="md:w-px md:whitespace-nowrap">College</th>
                            <th class="md:w-px md:whitespace-nowrap">Submitted</th>
                            <th class="md:w-px md:whitespace-nowrap">Screening</th>
                            <th class="md:w-px md:whitespace-nowrap">Status</th>
                            <th class="md:w-px"><span class="sr-only">Actions</span></th>
                        </x-slot:head>

                        @foreach ($queue as $application)
                            @php
                                $others = $application->applicant->applications->where('id', '!=', $application->id);
                                $topBadge = $others->map(fn ($other) => $other->outcomeBadge())->sortByDesc(fn ($badge) => $severity[$badge['color']])->first();
                                $reviewUrl = route('staff.applications.review', $application);
                            @endphp
                            <tr
                                wire:key="queue-{{ $application->id }}"
                                class="group cursor-pointer transition-colors hover:bg-paper/60"
                                x-bind:class="$wire.selected.includes('{{ $application->id }}') && 'bg-navy-50/40'"
                                x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $reviewUrl }}')"
                            >
                                <td class="col-start-1 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            wire:model="selected"
                                            value="{{ $application->id }}"
                                            x-on:click.stop
                                            aria-label="Select {{ $application->applicant->first_name }} {{ $application->applicant->last_name }}"
                                            class="rounded border-line text-navy-600 focus-visible:ring-2 focus-visible:ring-navy-600"
                                        >
                                        <div class="min-w-0">
                                            <a href="{{ $reviewUrl }}" wire:navigate x-on:click.stop class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                                {{ $application->applicant->last_name }}, {{ $application->applicant->first_name }}
                                            </a>
                                            <p class="flex items-center gap-2 font-mono text-[12px] text-mist">
                                                {{ $application->applicant->student_id }}
                                                @if ($topBadge)
                                                    <span class="border-l-2 border-{{ $topBadge['color'] }} pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-{{ $topBadge['color'] }}" title="Other application: {{ $topBadge['label'] }}">{{ $others->count() }} other</span>
                                                @endif
                                                <x-staff.holding-flag :flag="$holdingFlags[$application->applicant_id]" />
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap" title="{{ $application->college->name }}"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="building" class="size-3.5 text-mist" />{{ $application->college->code }}</span></td>
                                <td class="col-start-1 font-mono text-[12px] text-slate md:whitespace-nowrap"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="calendar" class="size-3.5 text-mist" />{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</span></td>
                                <td class="col-start-1 font-body text-[13px] md:whitespace-nowrap">
                                    @if ($application->failed_screenings_count > 0)
                                        <span class="inline-flex items-center gap-1.5 text-crimson-600">
                                            <x-ui.icon name="alert-circle" class="size-3.5" />
                                            <span class="font-mono">{{ $application->failed_screenings_count }}</span> failed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-navy-700">
                                            <x-ui.icon name="check-circle" class="size-3.5" />
                                            Passed
                                        </span>
                                    @endif
                                </td>
                                <td class="col-start-1 md:whitespace-nowrap"><x-ui.status-stamp :application="$application" /></td>
                                <td class="col-start-2 row-span-5 row-start-1 self-center" x-on:click.stop>
                                    <div class="flex justify-end">
                                        <x-ui.icon-button icon="chevron-right" label="Review {{ $application->applicant->first_name }} {{ $application->applicant->last_name }}" :href="$reviewUrl" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.list-table>
                @endif
            </div>
        </div>

        {{-- Shortlist --}}
        <div x-show="tab === 'shortlist'" x-cloak>
            <div class="flex flex-col gap-3 border-b border-line bg-paper/40 px-4 py-2.5 lg:flex-row lg:items-center">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-5 gap-y-1.5 font-body text-[13px]">
                    @if ($offering->hasScheduledInterview())
                        <span class="inline-flex min-w-0 items-center gap-1.5 text-ink" @if ($offering->interview_instructions) title="{{ $offering->interview_instructions }}" @endif>
                            <x-ui.icon name="calendar" class="size-4 shrink-0 text-navy-700" />
                            <span class="font-500">Interview</span>
                            <span class="font-mono text-[12px] text-slate">{{ $offering->interview_at->format('M j, Y · g:i A') }}</span>
                        </span>
                        <span class="inline-flex min-w-0 items-center gap-1.5 text-slate">
                            <x-ui.icon name="map-pin" class="size-4 shrink-0" />
                            <span class="truncate">{{ $offering->interview_venue }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-mist">
                            <x-ui.icon name="calendar" class="size-4" />
                            No interview scheduled
                        </span>
                    @endif

                    @if ($shortlistRequirementCount > 0)
                        @if ($requirementsOpened)
                            <span class="inline-flex items-center gap-1.5 text-navy-700">
                                <x-ui.icon name="check-circle" class="size-4" />
                                Shortlist requirements opened <span class="font-mono text-[12px] text-slate">{{ $offering->shortlist_requirements_opened_at->format('M j, Y') }}</span>
                            </span>
                        @elseif ($shortlistShort > 0)
                            <span class="inline-flex items-center gap-1.5 text-amber-600">
                                <x-ui.icon name="clock" class="size-4" />
                                Shortlist {{ $shortlistShort }} more {{ str('applicant')->plural($shortlistShort) }} to open the shortlist requirements.
                            </span>
                        @endif
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                    <x-ui.button-link variant="secondary" class="px-3 py-1.5 text-[13px]" :href="route('staff.applications.workspace.shortlist.export', $offering)">
                        <x-ui.icon name="download" class="size-4" />
                        Export shortlist
                    </x-ui.button-link>
                    @if ($isAdmin)
                        <x-ui.button-link variant="secondary" class="px-3 py-1.5 text-[13px]" :href="route('admin.offerings.configure', ['offering' => $offering, 'section' => 'interview'])" wire:navigate>
                            <x-ui.icon name="calendar" class="size-4" />
                            {{ $offering->hasScheduledInterview() ? 'Edit interview' : 'Schedule interview' }}
                        </x-ui.button-link>
                    @endif
                    @if ($shortlistRequirementCount > 0 && ! $requirementsOpened)
                        <x-ui.button
                            class="px-3 py-1.5 text-[13px]"
                            loading="openShortlistRequirements"
                            :disabled="$shortlistShort > 0"
                            x-on:click="$store.confirm.ask({
                                title: 'Open shortlist requirements?',
                                message: 'Every shortlisted applicant will see them on their tracking page. This can’t be undone.',
                                confirmLabel: 'Open requirements',
                                tone: 'primary',
                                action: () => $wire.openShortlistRequirements(),
                            })"
                        >
                            <x-ui.icon name="clipboard-check" class="size-4" />
                            Open shortlist requirements
                        </x-ui.button>
                    @endif
                </div>
            </div>

            <div wire:loading.delay.class="opacity-60" wire:target="search, college_id, award" class="transition-opacity">
                @if ($shortlist->isEmpty())
                    @if ($filtered)
                        <x-ui.list-empty icon="search" title="No shortlisted applicants match" message="Try another name, student ID or college.">
                            <x-ui.button variant="secondary" wire:click="$set('search', ''); $set('college_id', '')">Clear filters</x-ui.button>
                        </x-ui.list-empty>
                    @else
                        <x-ui.list-empty icon="shortlist" title="Nothing shortlisted yet" message="Endorse applications from the queue to add them here.">
                            <x-ui.button variant="secondary" x-on:click="tab = 'queue'">Open queue</x-ui.button>
                        </x-ui.list-empty>
                    @endif
                @else
                    <x-ui.list-table>
                        <x-slot:head>
                            <th>Applicant</th>
                            <th class="md:w-px md:whitespace-nowrap">College</th>
                            <th class="md:w-px md:whitespace-nowrap">Submitted</th>
                            @if ($shortlistRequirementCount > 0)
                                <th class="md:w-px md:whitespace-nowrap">Requirements</th>
                            @endif
                            <th class="md:w-px"><span class="sr-only">Actions</span></th>
                        </x-slot:head>

                        @foreach ($shortlist as $row)
                            @php
                                $application = $row['application'];
                                $outstanding = $shortlistRequirementCount - $row['complied'];
                                $reviewUrl = route('staff.applications.review', $application);
                                $fullName = $application->applicant->first_name.' '.$application->applicant->last_name;
                            @endphp
                            <tr
                                wire:key="shortlist-{{ $application->id }}"
                                class="group cursor-pointer transition-colors hover:bg-paper/60"
                                x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $reviewUrl }}')"
                            >
                                <td class="col-start-1 min-w-0">
                                    <a href="{{ $reviewUrl }}" wire:navigate x-on:click.stop class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                        {{ $application->applicant->last_name }}, {{ $application->applicant->first_name }}
                                    </a>
                                    <p class="flex items-center gap-2 font-mono text-[12px] text-mist">
                                        {{ $application->applicant->student_id }}
                                        <x-staff.holding-flag :flag="$holdingFlags[$application->applicant_id]" />
                                    </p>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap" title="{{ $application->college->name }}"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="building" class="size-3.5 text-mist" />{{ $application->college->code }}</span></td>
                                <td class="col-start-1 font-mono text-[12px] text-slate md:whitespace-nowrap"><span class="inline-flex items-center gap-1.5"><x-ui.icon name="calendar" class="size-3.5 text-mist" />{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</span></td>
                                @if ($shortlistRequirementCount > 0)
                                    <td class="col-start-1 font-body text-[13px] md:whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 {{ $outstanding === 0 ? 'text-navy-700' : 'text-amber-600' }}">
                                            <x-ui.icon :name="$outstanding === 0 ? 'check-circle' : 'clock'" class="size-3.5" />
                                            <span class="font-mono">{{ $row['complied'] }}/{{ $shortlistRequirementCount }}</span>
                                            {{ $outstanding === 0 ? 'complied' : 'outstanding' }}
                                        </span>
                                    </td>
                                @endif
                                <td class="col-start-2 row-span-4 row-start-1 self-center" x-on:click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($outstanding > 0 || $holdingFlags[$application->applicant_id]['national'])
                                            <span
                                                class="inline-flex h-8 cursor-not-allowed items-center gap-1.5 rounded-control px-3 font-body text-[13px] font-500 text-mist"
                                                title="{{ $holdingFlags[$application->applicant_id]['national'] ? 'Already holds a national scholarship' : ($requirementsOpened ? 'Every shortlist requirement must be complied with first' : 'Open the shortlist requirements first') }}"
                                            >
                                                <x-ui.icon name="award" class="size-4" />
                                                Award
                                            </span>
                                        @else
                                            <button
                                                type="button"
                                                wire:loading.attr="disabled"
                                                wire:target="award({{ $application->id }})"
                                                x-on:click="$store.confirm.ask({
                                                    title: {{ \Illuminate\Support\Js::from('Award '.$fullName.'?') }},
                                                    message: 'The award is recorded and the notice is emailed right away. This can’t be undone.',
                                                    confirmLabel: 'Record award',
                                                    tone: 'primary',
                                                    action: () => $wire.award({{ $application->id }}),
                                                })"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-control bg-navy-600 px-3 font-body text-[13px] font-500 text-white transition-colors hover:bg-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2 disabled:opacity-60"
                                            >
                                                <x-ui.icon name="award" class="size-4" />
                                                Award
                                            </button>
                                        @endif
                                        <x-ui.icon-button icon="chevron-right" label="Review {{ $fullName }}" :href="$reviewUrl" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.list-table>
                @endif
            </div>
        </div>
    </div>
</div>
