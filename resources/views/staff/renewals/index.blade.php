<?php

use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStage;
use App\Enums\UserRole;
use App\Livewire\Concerns\DeletesOfferings;
use App\Models\Application;
use App\Models\ProgramOffering;
use App\Services\RenewalService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Renewals'))] class extends Component
{
    use DeletesOfferings;

    public ProgramOffering $offering;

    #[Url]
    public string $status = 'screening';

    public string $search = '';

    public function mount(ProgramOffering $offering): void
    {
        abort_unless($offering->isRenewal(), 404);

        $this->offering = $offering->load('scholarshipProgram', 'academicTerm', 'renewsOffering');

        if (! in_array($this->status, ['screening', 'awarded', 'denied', 'all'], true)) {
            $this->status = 'screening';
        }
    }

    public function with(RenewalService $renewalService): array
    {
        $requirementCount = $this->offering->additionalRequirements()
            ->where('stage', RequirementStage::Renewal->value)
            ->count();

        $all = $this->offering->applications()
            ->where('kind', ApplicationKind::Renewal->value)
            ->with(['applicant', 'college', 'additionalRequirementSubmissions.requirement'])
            ->get();

        $renewals = $all
            ->when($this->status !== 'all', fn ($renewals) => $renewals->filter(fn (Application $renewal) => $renewal->status->value === $this->status))
            ->when($this->search !== '', function ($renewals) {
                $needle = mb_strtolower($this->search);

                return $renewals->filter(fn (Application $renewal) => str_contains(mb_strtolower($renewal->applicant->last_name.' '.$renewal->applicant->first_name.' '.$renewal->applicant->student_id), $needle));
            })
            ->sortBy(fn (Application $renewal) => $renewal->applicant->last_name.' '.$renewal->applicant->first_name)
            ->map(fn (Application $renewal) => [
                'application' => $renewal,
                'lastTerm' => $renewalService->isLastTerm($renewal->setRelation('programOffering', $this->offering)),
                'complied' => $renewal->additionalRequirementSubmissions
                    ->filter(fn ($submission) => $submission->requirement->stage === RequirementStage::Renewal && $submission->isComplied())
                    ->count(),
                'toReview' => $renewal->additionalRequirementSubmissions
                    ->filter(fn ($submission) => $submission->requirement->stage === RequirementStage::Renewal && $submission->file_path && $submission->status->value === 'pending')
                    ->count(),
            ])
            ->values();

        return [
            'renewals' => $renewals,
            'counts' => [
                'screening' => $all->where('status', ApplicationStatus::Screening)->count(),
                'awarded' => $all->where('status', ApplicationStatus::Awarded)->count(),
                'denied' => $all->where('status', ApplicationStatus::Denied)->count(),
                'all' => $all->count(),
            ],
            'chain' => $renewalService->chainOf($this->offering),
            'newApplicationCount' => $this->offering->takesNewApplicants()
                ? $this->offering->applications()
                    ->where('kind', ApplicationKind::New->value)
                    ->whereIn('status', [ApplicationStatus::Submitted->value, ApplicationStatus::Screening->value])
                    ->count()
                : null,
            'requirementCount' => $requirementCount,
            'dueCount' => auth()->user()->role === UserRole::Admin ? $renewalService->dueAwards($this->offering)->count() : 0,
        ];
    }
};
?>

@php
    $isAdmin = auth()->user()->role === UserRole::Admin;
@endphp

<div>
    <x-ui.back-link :href="route('staff.applications.index')" label="Applications" wire:navigate />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $chain->first()->displayName() }}</h1>
        <x-ui.button-link variant="secondary" :href="route('staff.grantees.index', ['program' => $offering->scholarship_program_id])" wire:navigate>
            <x-ui.icon name="users" class="size-4" />
            Grantees
        </x-ui.button-link>
    </div>

    <x-staff.offering-chain-tabs :chain="$chain" :current="$offering" class="mt-4 border-b border-line" />

    @if ($newApplicationCount !== null)
        <x-staff.renewal-offering-nav class="mt-4" :offering="$offering" current="renewals" :new-count="$newApplicationCount" :renewal-count="$counts['screening']" />
    @endif

    @if ($dueCount > 0 || $requirementCount === 0)
        <div class="mt-4 space-y-2">
            @if ($dueCount > 0)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-card border border-line border-l-2 border-l-azure-600 bg-surface px-4 py-2.5">
                    <x-ui.icon name="info" class="size-4 shrink-0 text-azure-600" />
                    <p class="min-w-0 flex-1 font-body text-[13px] text-ink">
                        <span class="font-mono">{{ $dueCount }}</span> {{ str('scholar')->plural($dueCount) }} from {{ $offering->renewsOffering->displayName() }} {{ $dueCount === 1 ? 'has' : 'have' }} no renewal yet.
                    </p>
                    <x-ui.button-link variant="ghost" class="px-2.5 py-1 text-[13px]" :href="route('admin.offerings.view', $offering)" wire:navigate>
                        Open renewals
                        <x-ui.icon name="arrow-right" class="size-3.5" />
                    </x-ui.button-link>
                </div>
            @endif
            @if ($requirementCount === 0)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-card border border-line border-l-2 border-l-amber-600 bg-surface px-4 py-2.5">
                    <x-ui.icon name="alert-circle" class="size-4 shrink-0 text-amber-600" />
                    <p class="min-w-0 flex-1 font-body text-[13px] text-ink">This offering has no renewal requirements, so scholars have nothing to hand in.</p>
                    @if ($isAdmin)
                        <x-ui.button-link variant="ghost" class="px-2.5 py-1 text-[13px]" :href="route('admin.offerings.configure', $offering)" wire:navigate>
                            Add renewal requirements
                            <x-ui.icon name="arrow-right" class="size-3.5" />
                        </x-ui.button-link>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search renewals"
                placeholder="Search by name or student ID"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <x-ui.segmented
                class="md:ml-auto"
                label="Filter renewals"
                model="status"
                :value="$status"
                :options="['screening' => 'To review', 'awarded' => 'Renewed', 'denied' => 'Denied', 'all' => 'All']"
                :counts="$counts"
                :alert="['screening']"
            />
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="status, search" class="transition-opacity">
            @if ($renewals->isEmpty())
                @if ($search !== '')
                    <x-ui.list-empty icon="search" title="No renewals match" message="Try another name or student ID.">
                        <x-ui.button variant="secondary" wire:click="$set('search', '')">Clear search</x-ui.button>
                    </x-ui.list-empty>
                @elseif ($counts['all'] === 0)
                    <x-ui.list-empty icon="clipboard-check" title="No renewals opened yet" message="The administrator opens renewals for {{ $offering->renewsOffering->displayName() }} scholars on this offering's page.">
                        @if ($isAdmin)
                            <x-ui.button-link :href="route('admin.offerings.view', $offering)" wire:navigate>Open renewals</x-ui.button-link>
                        @endif
                    </x-ui.list-empty>
                @else
                    <x-ui.list-empty icon="check-circle" title="No renewals here" message="The other filters hold the rest.">
                        <x-ui.button variant="secondary" wire:click="$set('status', 'all')">Show all renewals</x-ui.button>
                    </x-ui.list-empty>
                @endif
            @else
                <x-ui.list-table>
                    <x-slot:head>
                        <th class="w-[32%]">Scholar</th>
                        <th>College</th>
                        <th>Year level</th>
                        <th>Requirements</th>
                        <th>Status</th>
                        <th><span class="sr-only">Actions</span></th>
                    </x-slot:head>

                    @foreach ($renewals as $row)
                        @php
                            $renewalRow = $row['application'];
                            $reviewUrl = route('staff.renewals.review', $renewalRow);
                            $allComplied = $row['complied'] === $requirementCount;
                        @endphp
                        <tr
                            wire:key="renewal-{{ $renewalRow->id }}"
                            class="group cursor-pointer transition-colors hover:bg-paper/60"
                            x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $reviewUrl }}')"
                        >
                            <td class="col-start-1 min-w-0">
                                <a href="{{ $reviewUrl }}" wire:navigate x-on:click.stop class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                    {{ $renewalRow->applicant->last_name }}, {{ $renewalRow->applicant->first_name }}
                                </a>
                                <p class="flex items-center gap-2 font-mono text-[12px] text-mist">
                                    {{ $renewalRow->applicant->student_id }}
                                    @if ($row['lastTerm'])
                                        <span class="border-l-2 border-azure-600 pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-azure-600">Last term</span>
                                    @endif
                                </p>
                            </td>
                            <td class="col-start-1 font-body text-[13px] text-slate" title="{{ $renewalRow->college->name }}">{{ $renewalRow->college->code }}</td>
                            <td class="col-start-1 font-body text-[13px]">
                                @if ($renewalRow->year_level === null)
                                    <span class="inline-flex items-center gap-1.5 text-amber-600">
                                        <x-ui.icon name="clock" class="size-3.5" />
                                        Not entered
                                    </span>
                                @else
                                    <span class="md:hidden text-mist">Year</span>
                                    <span class="font-mono text-ink">{{ $renewalRow->year_level }}</span>
                                @endif
                            </td>
                            <td class="col-start-1 font-body text-[13px]">
                                <span class="inline-flex items-center gap-1.5 {{ $allComplied ? 'text-navy-700' : 'text-amber-600' }}">
                                    <x-ui.icon :name="$allComplied ? 'check-circle' : 'clock'" class="size-3.5" />
                                    <span class="font-mono">{{ $row['complied'] }}/{{ $requirementCount }}</span>
                                    complied
                                </span>
                                @if ($row['toReview'] > 0)
                                    <span class="ml-1 font-body text-[12px] text-amber-600">&middot; <span class="font-mono">{{ $row['toReview'] }}</span> to review</span>
                                @endif
                            </td>
                            <td class="col-start-1"><x-ui.status-stamp :application="$renewalRow" /></td>
                            <td class="col-start-2 row-span-5 row-start-1 self-center" x-on:click.stop>
                                <div class="flex justify-end">
                                    <x-ui.icon-button icon="chevron-right" label="Review {{ $renewalRow->applicant->first_name }} {{ $renewalRow->applicant->last_name }}" :href="$reviewUrl" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.list-table>
            @endif
        </div>
    </div>
</div>
