<?php

use App\Enums\AllocationMode;
use App\Models\ProgramOffering;
use App\Services\OfferingService;
use App\Services\RenewalService;
use App\Livewire\Concerns\DeletesOfferings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Offering'))] class extends Component
{
    use DeletesOfferings;

    public ProgramOffering $offering;

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm', 'renewsOffering');
    }

    protected function chainLinksToAdmin(): bool
    {
        return true;
    }

    public function isQuota(): bool
    {
        return $this->offering->allocation_mode === AllocationMode::Quota;
    }

    public function openRenewals(RenewalService $renewalService): void
    {
        try {
            $opened = $renewalService->openRenewals($this->offering, auth()->user());
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Renewals not opened', message: $exception->getMessage());

            return;
        }

        $this->dispatch(
            'notice',
            title: $opened === 0 ? 'No scholars are due for renewal' : "Opened {$opened} ".str('renewal')->plural($opened),
            message: $opened === 0 ? '' : 'Each scholar was emailed to hand in the renewal requirements.',
        );
    }

    public function with(OfferingService $offeringService, RenewalService $renewalService): array
    {
        return [
            ...$offeringService->summary($this->offering),
            'chain' => $renewalService->chainOf($this->offering),
            'dueAwards' => $renewalService->dueAwards($this->offering),
        ];
    }
};
?>

@php
    $stats = [
        ['icon' => 'award', 'label' => 'Award per scholar', 'value' => '₱'.number_format($offering->amount, 2)],
    ];

    if ($offering->isRenewal()) {
        $stats[] = ['icon' => 'calendar', 'label' => 'Submission window', 'value' => $offering->opens_at->format('M j').' – '.$offering->closes_at->format('M j, Y'), 'sub' => 'Due '.$offering->closes_at->format('g:i A'), 'small' => true];
    }

    $stats[] = ['icon' => 'chart-bar', 'label' => 'Total allocation', 'value' => '₱'.number_format($allocation, 2), 'sub' => '₱'.number_format($awardedAmount, 2).' committed'];

    if ($offering->isRenewal()) {
        $stats[] = ['icon' => 'users', 'label' => 'Renewing scholars', 'value' => $renewals, 'href' => route('staff.renewals.index', $offering), 'link' => 'Review renewals'];
        $stats[] = ['icon' => 'check-circle', 'label' => 'Renewed', 'value' => $renewed, 'of' => $renewals];
    }

    if ($offering->takesNewApplicants()) {
        $stats[] = ['icon' => 'users', 'label' => 'Applicants', 'value' => $applied, 'href' => route('staff.applications.workspace', $offering), 'link' => 'Review applicants'];
        $stats[] = ['icon' => 'award', 'label' => 'Awarded', 'value' => $awarded, 'of' => $offering->total_slots, 'sub' => 'Slots filled'];
    }
@endphp

<div>
    <x-ui.back-link
        :href="route('admin.scholarship-programs.offerings', $offering->scholarshipProgram)"
        label="{{ $offering->scholarshipProgram->name }} offerings"
    />

    <h1 class="mt-3 font-display text-[22px] font-600 text-ink sm:text-[26px]">
        {{ $offering->scholarshipProgram->name }} · {{ $chain->first()->displayName() }}
    </h1>

    {{-- The first offering and every renewal offering that continues it --}}
    <div class="mt-4 flex flex-wrap items-end justify-between gap-3 border-b border-line">
        <x-staff.offering-chain-tabs :chain="$chain" :current="$offering" admin />
        <a
            href="{{ route('admin.scholarship-programs.offerings', ['scholarshipProgram' => $offering->scholarshipProgram, 'renews' => $chain->last()->id]) }}"
            wire:navigate
            class="mb-2 inline-flex items-center gap-1 rounded-control px-3 py-1.5 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
        >
            <x-ui.icon name="plus" class="size-4" /> Add renewal offering
        </a>
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            <h2 class="font-display text-[18px] font-600 text-ink">{{ $offering->displayName() }}</h2>
            <x-ui.status-stamp :status="$offering->registrationStatus()" />
            @if ($offering->isRenewal())
                <span class="inline-flex items-center gap-1.5 font-body text-[13px] text-slate">
                    <x-ui.icon name="layers" class="size-4 text-mist" />
                    Renews {{ $offering->renewsOffering->displayName() }}{{ $offering->accepts_new_applicants ? ' · takes new applicants' : '' }}
                </span>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <x-ui.button-link variant="secondary" :href="route('admin.offerings.configure', $offering)" wire:navigate>
                <x-ui.icon name="clipboard-check" class="size-4" />
                Requirements
            </x-ui.button-link>
            <x-ui.button-link :href="route('admin.scholarship-programs.offerings', ['scholarshipProgram' => $offering->scholarshipProgram, 'edit' => $offering->id])" wire:navigate>
                <x-ui.icon name="pencil" class="size-4" />
                Edit offering
            </x-ui.button-link>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 {{ count($stats) > 4 ? 'lg:grid-cols-3 xl:grid-cols-5' : 'lg:grid-cols-4' }}">
        @foreach ($stats as $stat)
            @php($tag = isset($stat['href']) ? 'a' : 'div')
            <{{ $tag }}
                @isset($stat['href']) href="{{ $stat['href'] }}" wire:navigate @endisset
                @class([
                    'flex items-center gap-3 rounded-card border border-line bg-surface p-4',
                    'transition-colors hover:border-navy-100 hover:bg-paper/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600' => isset($stat['href']),
                ])
            >
                <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                    <x-ui.icon :name="$stat['icon']" class="size-5" />
                </span>
                <span class="min-w-0">
                    <span class="block font-body text-[12px] text-slate">{{ $stat['label'] }}</span>
                    <span class="block truncate font-mono {{ ($stat['small'] ?? false) ? 'text-[14px]' : 'text-[18px]' }} leading-tight text-ink">
                        {{ $stat['value'] }}@isset($stat['of'])<span class="text-[13px] text-mist"> / {{ $stat['of'] }}</span>@endisset
                    </span>
                    @isset($stat['link'])
                        <span class="block font-body text-[12px] font-500 text-navy-700">{{ $stat['link'] }}</span>
                    @elseif (isset($stat['sub']))
                        <span class="block truncate font-body text-[12px] text-mist">{{ $stat['sub'] }}</span>
                    @endisset
                </span>
            </{{ $tag }}>
        @endforeach
    </div>

    @if ($offering->isRenewal())
        <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <div>
                    <h2 class="font-display text-[15px] font-600 text-ink">Scholars due to renew</h2>
                    <p class="font-body text-[12px] text-mist">
                        Awarded in {{ $offering->renewsOffering->displayName() }} · <span class="font-mono text-slate">{{ $dueAwards->count() }}</span> without a renewal yet
                    </p>
                </div>

                @if ($dueAwards->isNotEmpty())
                    @php($dueLabel = $dueAwards->count().' '.str('scholar')->plural($dueAwards->count()))
                    <x-ui.button
                        loading="openRenewals"
                        x-on:click="$store.confirm.ask({
                            title: {{ \Illuminate\Support\Js::from('Open renewals for '.$dueLabel.'?') }},
                            message: 'Each scholar is emailed to hand in the renewal requirements.',
                            confirmLabel: 'Open renewals',
                            tone: 'primary',
                            action: () => $wire.openRenewals(),
                        })"
                    >
                        Open renewals for {{ $dueLabel }}
                    </x-ui.button>
                @endif
            </div>

            @if ($dueAwards->isNotEmpty())
                <ul class="divide-y divide-line">
                    @foreach ($dueAwards as $award)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2" wire:key="due-award-{{ $award->id }}">
                            <span class="font-body text-[14px] text-ink">
                                {{ $award->applicant->last_name }}, {{ $award->applicant->first_name }}
                                <span class="ml-1 font-mono text-[12px] text-slate">{{ $award->applicant->student_id }}</span>
                            </span>
                            <span class="font-mono text-[12px] text-mist">Awarded {{ $award->awarded_at->format('M j, Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @elseif ($renewals === 0)
                <p class="px-4 py-6 text-center font-body text-[13px] text-slate">
                    No scholars were awarded in {{ $offering->renewsOffering->displayName() }} yet.
                    <a href="{{ route('admin.offerings.view', $offering->renewsOffering) }}" wire:navigate class="font-500 text-navy-700 hover:underline">Go to {{ $offering->renewsOffering->displayName() }}</a>
                </p>
            @else
                <p class="px-4 py-6 text-center font-body text-[13px] text-mist">Every scholar has a renewal.</p>
            @endif
        </div>
    @endif

    @if ($offering->takesNewApplicants())
        <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 class="font-display text-[15px] font-600 text-ink">By college</h2>
                <span class="font-mono text-[12px] text-mist">{{ $perCollege->count() }}</span>
            </div>

            @if ($perCollege->isEmpty())
                <div class="px-6 py-10 text-center">
                    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                        <x-ui.icon name="building" class="size-6" />
                    </span>
                    <p class="mt-3 font-display text-[16px] font-600 text-ink">No applicants yet</p>
                    <p class="mt-1 font-body text-[14px] text-slate">Counts per college appear once students apply.</p>
                    <x-ui.button-link variant="secondary" class="mt-4" :href="route('staff.applications.workspace', $offering)" wire:navigate>Review applicants</x-ui.button-link>
                </div>
            @else
                <table class="w-full font-body text-[13px]">
                    <thead class="border-b border-line bg-paper/60">
                        <tr>
                            <th class="px-4 py-2 text-left text-[11px] font-600 uppercase tracking-[0.08em] text-mist">College</th>
                            <th class="px-4 py-2 text-right text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Applied</th>
                            <th class="px-4 py-2 text-right text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Awarded</th>
                            @if ($this->isQuota())
                                <th class="px-4 py-2 text-right text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Slots</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($perCollege as $row)
                            <tr wire:key="offering-college-{{ $row['college']->id }}" class="transition-colors hover:bg-paper/60">
                                <td class="px-4 py-2.5 text-ink">
                                    <span class="hidden sm:inline">{{ $row['college']->name }}</span>
                                    <span class="sm:hidden">{{ $row['college']->code }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink">{{ $row['applied'] }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink">{{ $row['awarded'] }}</td>
                                @if ($this->isQuota())
                                    <td class="px-4 py-2.5 text-right font-mono text-slate">{{ $row['slots'] ?? '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
