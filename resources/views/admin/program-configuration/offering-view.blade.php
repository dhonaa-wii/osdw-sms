<?php

use App\Enums\AllocationMode;
use App\Models\ProgramOffering;
use App\Services\OfferingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Offering'))] class extends Component
{
    public ProgramOffering $offering;

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm');
    }

    public function isQuota(): bool
    {
        return $this->offering->allocation_mode === AllocationMode::Quota;
    }

    public function with(OfferingService $offeringService): array
    {
        return $offeringService->summary($this->offering);
    }
};
?>

<div>
    <x-ui.back-link
        :href="route('admin.scholarship-programs.offerings', $offering->scholarshipProgram)"
        label="{{ $offering->scholarshipProgram->name }} offerings"
    />

    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-[22px] font-600 text-ink sm:text-[26px] lg:text-[30px]">
                {{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}
                @if ($offering->name)
                    <span class="text-mist">&middot; {{ $offering->academicTerm->school_year }} {{ $offering->academicTerm->semester->label() }}</span>
                @endif
            </h1>
            <x-ui.status-stamp :status="$offering->registrationStatus()" />
        </div>

        <div class="flex flex-wrap gap-3">
            <x-ui.button-link variant="secondary" :href="route('admin.offerings.configure', $offering)">Requirements</x-ui.button-link>
            <x-ui.button-link :href="route('admin.scholarship-programs.offerings', ['scholarshipProgram' => $offering->scholarshipProgram, 'edit' => $offering->id])">Edit offering</x-ui.button-link>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Award per scholar</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">
                @if ($offering->amount !== null)
                    &#8369;{{ number_format($offering->amount, 2) }}
                @elseif ($offering->discount_rate !== null)
                    {{ rtrim(rtrim(number_format($offering->discount_rate, 2), '0'), '.') }}%
                @else
                    &mdash;
                @endif
            </p>
            @if ($offering->amount === null && $offering->discount_rate !== null)
                <p class="mt-1 font-body text-[12px] text-slate">Tuition discount.</p>
            @endif
        </div>

        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Total allocation</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">
                @if ($allocation === null)
                    &mdash;
                @else
                    &#8369;{{ number_format($allocation, 2) }}
                @endif
            </p>
            <p class="mt-1 font-body text-[12px] text-slate">
                &#8369;{{ number_format($awardedAmount, 2) }} committed to awards so far.
            </p>
        </div>

        <a
            href="{{ route('staff.applications.workspace', $offering) }}"
            class="rounded-card border border-line bg-surface p-4 transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2"
        >
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Applicants</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">{{ $applied }}</p>
            <p class="mt-1 font-body text-[12px] font-500 text-forest-700">Review applicants</p>
        </a>

        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Approved</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">{{ $awarded }} <span class="text-[14px] text-mist">/ {{ $offering->total_slots }}</span></p>
            <p class="mt-1 font-body text-[12px] text-slate">Slots filled.</p>
        </div>
    </div>

    <h2 class="mt-8 font-display text-[20px] font-600 text-ink">By college</h2>

    <div class="mt-4">
        @if ($perCollege->isEmpty())
            <x-ui.empty-state
                title="No applicants yet"
                message="Counts per college appear once students start applying to this offering."
            >
                <x-slot:action>
                    <x-ui.button-link variant="secondary" :href="route('staff.applications.workspace', $offering)">Review applicants</x-ui.button-link>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="array_values(array_filter([
                    ['key' => 'college', 'label' => 'College', 'sortable' => false],
                    ['key' => 'applied', 'label' => 'Applied', 'sortable' => false],
                    ['key' => 'awarded', 'label' => 'Approved', 'sortable' => false],
                    $this->isQuota() ? ['key' => 'slots', 'label' => 'Slots', 'sortable' => false] : null,
                ]))"
                :rows="null"
            >
                @foreach ($perCollege as $row)
                    <tr wire:key="offering-college-{{ $row['college']->id }}" class="transition hover:bg-paper">
                        <td data-label="College" class="text-[14px] text-ink">{{ $row['college']->name }}</td>
                        <td data-label="Applied" class="font-mono text-[14px] text-ink">{{ $row['applied'] }}</td>
                        <td data-label="Approved" class="font-mono text-[14px] text-ink">{{ $row['awarded'] }}</td>
                        @if ($this->isQuota())
                            <td data-label="Slots" class="font-mono text-[14px] text-slate">
                                @if ($row['slots'] === null)
                                    &mdash;
                                @else
                                    {{ $row['slots'] }}
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>
</div>
