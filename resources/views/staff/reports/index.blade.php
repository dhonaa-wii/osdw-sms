<?php

use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use App\Services\RenewalService;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Reports'))] class extends Component
{
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

    public function updatedScholarshipProgramId(): void
    {
        $this->group_id = '';
        $this->offering_id = '';
    }

    public function updatedGroupId(): void
    {
        $this->offering_id = '';
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'college_id', 'scholarship_program_id', 'group_id', 'offering_id');
    }

    public function with(RenewalService $renewalService, ReportService $reportService): array
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
        $selectedOffering = $chain->firstWhere('id', (int) $this->offering_id) ?? $chain->last();

        $programId = $this->scholarship_program_id !== '' ? (int) $this->scholarship_program_id : null;
        $collegeId = $this->college_id !== '' ? (int) $this->college_id : null;
        $reportFilters = array_filter([
            'q' => trim($this->search),
            'program' => $programId,
            'offering' => $selectedOffering?->id,
            'college' => $collegeId,
        ]);

        return [
            'sucLevelling' => $reportService->sucLevelling(
                $reportService->granteeQuery($this->search, $programId, $selectedOffering, $collegeId)->get()
            ),
            'colleges' => College::orderBy('name')->get(),
            'scholarshipPrograms' => ScholarshipProgram::orderBy('name')->get(),
            'groups' => $groups,
            'chain' => $chain,
            'selectedOffering' => $selectedOffering,
            'filtered' => trim($this->search) !== '' || $this->college_id !== '' || $this->scholarship_program_id !== '',
            'exportUrl' => route('staff.reports.suc-levelling.export', $reportFilters),
            'printUrl' => route('staff.reports.suc-levelling.print', $reportFilters),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Reports</h1>
        @if ($sucLevelling->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                {{-- Loads the print view into a hidden frame and prints only that frame, so the page itself never prints. --}}
                <div
                    x-data="{
                        printing: false,
                        print(url) {
                            this.printing = true;
                            this.$refs.frame.onload = () => {
                                this.printing = false;
                                this.$refs.frame.contentWindow.focus();
                                this.$refs.frame.contentWindow.print();
                            };
                            this.$refs.frame.src = url;
                        },
                    }"
                >
                    <x-ui.button type="button" variant="secondary" x-on:click="print(@js($printUrl))" x-bind:disabled="printing">
                        <x-ui.icon name="printer" class="size-4" />
                        <span x-text="printing ? 'Preparing…' : 'Print report'">Print report</span>
                    </x-ui.button>
                    <iframe x-ref="frame" title="SUC-Levelling report print view" class="pointer-events-none fixed bottom-0 right-0 size-0 border-0" aria-hidden="true" tabindex="-1" wire:ignore></iframe>
                </div>
                <x-ui.button-link :href="$exportUrl">
                    <x-ui.icon name="download" class="size-4" />
                    Export .xlsx
                </x-ui.button-link>
            </div>
        @endif
    </div>

    @if ($selectedOffering)
        <x-staff.offering-chain-tabs
            class="mt-4 border-b border-line"
            :chain="$chain"
            :current="$selectedOffering"
            :manage="false"
            :href-for="fn ($member) => route('staff.reports.index', array_filter(['q' => $search, 'college' => $college_id, 'program' => $scholarship_program_id, 'group' => $group_id, 'offering' => $member->id]))"
        />
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

        <div class="flex items-center gap-2.5 border-b border-line px-4 py-2.5">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-700">
                <x-ui.icon name="chart-bar" class="size-4" />
            </span>
            <h2 class="min-w-0 flex-1 truncate font-display text-[15px] font-600 text-ink">Scholarship grantees</h2>
            <span class="shrink-0 font-body text-[13px] text-slate"><span class="font-mono text-ink">{{ $sucLevelling->count() }}</span> {{ str('grantee')->plural($sucLevelling->count()) }}</span>
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="search, college_id, scholarship_program_id, group_id, clearFilters" class="transition-opacity">
            @if ($sucLevelling->isEmpty())
                @if ($filtered)
                    <x-ui.list-empty icon="search" title="No grantees match" message="Try another name or student ID, or clear the filters.">
                        <x-ui.button variant="secondary" wire:click="clearFilters">Clear filters</x-ui.button>
                    </x-ui.list-empty>
                @else
                    <x-ui.list-empty icon="chart-bar" title="No grantees recorded yet" message="Award applicants from an offering's Shortlist tab to include them here.">
                        <x-ui.button-link :href="route('staff.applications.index')" wire:navigate>Go to applications</x-ui.button-link>
                    </x-ui.list-empty>
                @endif
            @else
                <div class="overflow-x-auto">
                    <table class="w-full font-body text-[13px]">
                        <thead class="border-b border-line bg-paper/60">
                            <tr>
                                @foreach (array_keys($sucLevelling->first()) as $header)
                                    <th class="whitespace-nowrap px-3 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist first:pl-4 last:pr-4">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($sucLevelling as $row)
                                <tr wire:key="report-row-{{ $loop->index }}" class="transition-colors hover:bg-paper/60">
                                    @foreach ($row as $value)
                                        <td class="whitespace-nowrap px-3 py-2 text-ink first:pl-4 last:pr-4">{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
