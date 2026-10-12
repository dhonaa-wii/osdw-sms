<?php

use App\Enums\ApplicationStatus;
use App\Enums\OfferingStatus;
use App\Models\ProgramOffering;
use App\Services\RenewalService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Applications'))] class extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    public function with(RenewalService $renewalService): array
    {
        // One row per group: the first offering stands for every renewal
        // offering that continues it, which open as tabs on the review page.
        $groups = ProgramOffering::query()
            ->whereNull('renews_offering_id')
            ->where('opens_at', '<=', now())
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhereHas('scholarshipProgram', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))))
            ->with('scholarshipProgram', 'academicTerm')
            ->orderByDesc('closes_at')
            ->get()
            ->map(function (ProgramOffering $first) use ($renewalService) {
                $chain = $renewalService->chainOf($first)
                    ->load('scholarshipProgram', 'academicTerm')
                    ->loadCount([
                        'applications',
                        'applications as to_review_count' => fn ($query) => $query->whereIn('status', [
                            ApplicationStatus::Submitted->value,
                            ApplicationStatus::Screening->value,
                        ]),
                    ]);
                $latest = $chain->last();

                return [
                    'first' => $first,
                    'latest' => $latest,
                    'status' => $latest->registrationStatus(),
                    'renewalOfferings' => $chain->count() - 1,
                    'applications' => $chain->sum('applications_count'),
                    'toReview' => $chain->sum('to_review_count'),
                ];
            });

        $counts = ['all' => $groups->count()];
        foreach (OfferingStatus::cases() as $case) {
            $counts[$case->value] = $groups->where('status', $case)->count();
        }

        return [
            'groups' => $this->status === 'all' ? $groups : $groups->filter(fn ($group) => $group['status']->value === $this->status),
            'counts' => $counts,
        ];
    }
};
?>

<div>
    <h1 class="font-display text-[26px] font-600 text-ink">Applications</h1>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search applications"
                placeholder="Search by program or offering"
                wire:model.live.debounce.300ms="search"
                class="md:w-80"
            />

            <x-ui.segmented
                class="md:ml-auto"
                label="Filter by status"
                model="status"
                :value="$status"
                :options="['all' => 'All', 'open' => 'Open', 'upcoming' => 'Upcoming', 'closed' => 'Closed']"
                :counts="$counts"
            />
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="search, status" class="transition-opacity">
            @if ($groups->isEmpty())
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                        <x-ui.icon name="form" class="size-6" />
                    </span>
                    @if ($search || $status !== 'all')
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No offerings match</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Try another search or clear the filters.</p>
                        <x-ui.button variant="secondary" class="mt-4" wire:click="$set('search', ''); $set('status', 'all')">Clear filters</x-ui.button>
                    @else
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No offerings to review</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Offerings appear here once their application window opens.</p>
                    @endif
                </div>
            @else
                <table class="w-full
                    [&_thead]:hidden md:[&_thead]:table-header-group
                    [&_tbody_tr]:grid [&_tbody_tr]:grid-cols-[1fr_auto] [&_tbody_tr]:gap-x-3 [&_tbody_tr]:gap-y-1 [&_tbody_tr]:px-4 [&_tbody_tr]:py-3
                    md:[&_tbody_tr]:table-row md:[&_tbody_tr]:p-0
                    md:[&_td]:table-cell md:[&_td]:px-4 md:[&_td]:py-2.5">
                    <thead class="border-b border-line bg-paper/60">
                        <tr>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Program</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Latest term</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">To review</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Total</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Status</th>
                            <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($groups as $group)
                            @php
                                $latest = $group['latest'];
                                $reviewUrl = $latest->isRenewal() ? route('staff.renewals.index', $latest) : route('staff.applications.workspace', $latest);
                            @endphp
                            <tr
                                wire:key="offering-group-{{ $group['first']->id }}"
                                class="group cursor-pointer transition-colors hover:bg-paper/60"
                                x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $reviewUrl }}')"
                            >
                                <td class="col-start-1 min-w-0 md:max-w-0 md:w-[38%]">
                                    <a
                                        href="{{ $reviewUrl }}"
                                        wire:navigate
                                        x-on:click.stop
                                        class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                    >{{ $group['first']->scholarshipProgram->name }}</a>
                                    <p class="truncate font-body text-[12px] text-mist">
                                        {{ $group['first']->displayName() }}
                                        @if ($group['renewalOfferings'] > 0)
                                            &middot; {{ $group['renewalOfferings'] }} {{ str('renewal')->plural($group['renewalOfferings']) }}
                                        @endif
                                    </p>
                                </td>
                                <td class="col-start-1 min-w-0 font-body text-[13px]">
                                    <p class="truncate text-slate">{{ $latest->displayName() }}</p>
                                    <p class="text-[12px] text-mist">{{ $latest->academicTerm->school_year }} &middot; {{ $latest->academicTerm->semester->label() }}</p>
                                </td>
                                <td class="col-start-1 font-body text-[13px]">
                                    @if ($group['toReview'] > 0)
                                        <span class="inline-flex items-center gap-1.5 text-amber-600">
                                            <x-ui.icon name="clock" class="size-3.5" />
                                            <span class="font-mono">{{ $group['toReview'] }}</span>
                                            <span class="md:hidden">to review</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-mist">
                                            <x-ui.icon name="check" class="size-3.5" />
                                            <span class="md:hidden">Nothing to review</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate">
                                    <span class="font-mono text-ink">{{ $group['applications'] }}</span>
                                    <span class="md:hidden">{{ str('application')->plural($group['applications']) }}</span>
                                </td>
                                <td class="col-start-1">
                                    <x-ui.status-stamp :status="$group['status']" />
                                </td>
                                <td class="col-start-2 row-span-5 row-start-1 self-center" x-on:click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-ui.icon-button
                                            icon="users"
                                            label="Grantees of {{ $group['first']->scholarshipProgram->name }}"
                                            :href="route('staff.grantees.index', ['program' => $group['first']->scholarship_program_id])"
                                        />
                                        <x-ui.icon-button icon="chevron-right" label="Review {{ $group['first']->scholarshipProgram->name }}" :href="$reviewUrl" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
