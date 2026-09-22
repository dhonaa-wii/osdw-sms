<?php

use App\Models\AcademicTerm;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Reports'))] class extends Component
{
    public string $academic_term_id = '';

    public function mount(): void
    {
        $this->academic_term_id = (string) (AcademicTerm::where('is_current', true)->first()?->id ?? '');
    }

    public function with(): array
    {
        $term = $this->academic_term_id ? AcademicTerm::find($this->academic_term_id) : null;
        $service = app(ReportService::class);

        return [
            'terms' => AcademicTerm::orderByDesc('start_date')->get(),
            'term' => $term,
            'sucLevelling' => $term ? $service->sucLevelling($term) : collect(),
            'perCollege' => $term ? $service->scholarsPerCollege($term) : collect(),
            'perFundingSource' => $term ? $service->scholarsPerFundingSource($term) : collect(),
            'funnel' => $term ? $service->applicationFunnel($term) : null,
            'retention' => $term ? $service->retentionReport($term) : null,
            'multipleAwards' => $term ? $service->multipleAwardReport($term) : collect(),
            'disqualifications' => $term ? $service->disqualificationReport($term) : collect(),
        ];
    }
};
?>

<div>
    <h1 class="font-display text-[30px] font-600 text-ink">Reports</h1>

    <div class="mt-6 max-w-xs">
        <x-ui.select name="academic_term_id" label="Term" wire:model.live="academic_term_id" placeholder="Select a term">
            @foreach ($terms as $option)
                <option value="{{ $option->id }}">{{ $option->school_year }} &middot; {{ $option->semester->label() }}{{ $option->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </x-ui.select>
    </div>

    @if (! $term)
        <x-ui.alert type="warning" class="mt-6">Select a term to view its reports.</x-ui.alert>
    @else
        <div class="mt-8 rounded-card border border-line bg-surface p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M9 8h6M9 12h6M9 16h4" /></svg>
                    </span>
                    <div>
                        <p class="font-display text-[18px] font-600 text-ink">SUC-Levelling summary</p>
                        <p class="mt-1 font-body text-[13px] text-slate">{{ $sucLevelling->count() }} active award(s) this term.</p>
                    </div>
                </div>
                <a href="{{ route('staff.reports.suc-levelling.export', $term) }}" class="rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white hover:bg-forest-700">Export .xlsx</a>
            </div>

            @if ($sucLevelling->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full font-body text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-mist">
                                @foreach (array_keys($sucLevelling->first()) as $header)
                                    <th class="px-2 py-2 font-600 uppercase tracking-[0.06em] text-[11px]">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($sucLevelling as $row)
                                <tr>
                                    @foreach ($row as $value)
                                        <td class="px-2 py-2 text-ink">{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="mt-6 rounded-card border border-line bg-surface p-5">
            <p class="font-display text-[16px] font-600 text-ink">Custom date range export</p>
            <p class="mt-1 font-body text-[13px] text-slate">SUC-Levelling data for any range of award dates, independent of term boundaries.</p>
            <form method="GET" action="{{ route('staff.reports.suc-levelling.export-range') }}" class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="range_from" class="block font-body text-[13px] font-500 text-ink">From</label>
                    <input type="date" id="range_from" name="from" class="mt-1 rounded-control border border-line bg-surface px-3 py-2 text-[14px] text-ink">
                </div>
                <div>
                    <label for="range_to" class="block font-body text-[13px] font-500 text-ink">To</label>
                    <input type="date" id="range_to" name="to" class="mt-1 rounded-control border border-line bg-surface px-3 py-2 text-[14px] text-ink">
                </div>
                <button type="submit" class="rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white hover:bg-forest-700">Export .xlsx</button>
            </form>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-card border border-line bg-surface p-5">
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-forest-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V8l8-5 8 5v13" /><path d="M10 21v-6h4v6" /></svg>
                    <p class="font-display text-[16px] font-600 text-ink">Scholars per college</p>
                </div>
                <div class="mt-3 space-y-3">
                    @php $maxCollegeAmount = $perCollege->max('amount'); @endphp
                    @forelse ($perCollege as $row)
                        @php $percent = $maxCollegeAmount > 0 ? min(100, round($row['amount'] / $maxCollegeAmount * 100)) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between font-body text-[14px]">
                                <span class="text-ink">{{ $row['college'] }}</span>
                                <span class="font-mono text-slate">{{ $row['scholars'] }} &middot; &#8369;{{ number_format($row['amount'], 2) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-paper">
                                <div class="h-1.5 rounded-full bg-forest-600" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="font-body text-[14px] text-mist">No active awards this term.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5">
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-forest-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="3" /></svg>
                    <p class="font-display text-[16px] font-600 text-ink">Scholars per funding source</p>
                </div>
                <div class="mt-3 space-y-3">
                    @php $maxFundingAmount = $perFundingSource->max('amount'); @endphp
                    @forelse ($perFundingSource as $row)
                        @php $percent = $maxFundingAmount > 0 ? min(100, round($row['amount'] / $maxFundingAmount * 100)) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between font-body text-[14px]">
                                <span class="text-ink">{{ $row['funding_source'] }}</span>
                                <span class="font-mono text-slate">{{ $row['scholars'] }} &middot; &#8369;{{ number_format($row['amount'], 2) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-paper">
                                <div class="h-1.5 rounded-full bg-forest-600" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="font-body text-[14px] text-mist">No active awards this term.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5 lg:col-span-2">
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-forest-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h18l-7 9v6l-4 2v-8Z" /></svg>
                    <p class="font-display text-[16px] font-600 text-ink">Application funnel</p>
                </div>
                <div class="mt-3 space-y-3">
                    @php
                        $funnelBase = max($funnel['received'], 1);
                        $funnelStages = [
                            ['label' => 'Received', 'value' => $funnel['received']],
                            ['label' => 'Eligible', 'value' => $funnel['eligible']],
                            ['label' => 'Shortlisted', 'value' => $funnel['shortlisted']],
                            ['label' => 'Awarded', 'value' => $funnel['awarded']],
                            ['label' => 'Accepted', 'value' => $funnel['accepted']],
                        ];
                    @endphp
                    @foreach ($funnelStages as $stage)
                        @php $percent = min(100, round($stage['value'] / $funnelBase * 100)); @endphp
                        <div>
                            <div class="flex items-center justify-between font-body text-[14px]">
                                <span class="text-ink">{{ $stage['label'] }}</span>
                                <span class="font-mono text-slate">{{ $stage['value'] }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-paper">
                                <div class="h-1.5 rounded-full bg-azure-600" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-display text-[16px] font-600 text-ink">Retention</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7" /></svg>
                        </span>
                        <div>
                            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Continuing</p>
                            <p class="font-mono text-[20px] leading-tight text-ink">{{ $retention['continuing'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-crimson-600/10 text-crimson-600">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                        </span>
                        <div>
                            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Terminated</p>
                            <p class="font-mono text-[20px] leading-tight text-ink">{{ $retention['terminated'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-display text-[16px] font-600 text-ink">Multiple-award holders</p>
                <div class="mt-3 space-y-2.5">
                    @forelse ($multipleAwards as $row)
                        <div class="flex items-center gap-2.5 font-body text-[14px]">
                            <svg class="size-4 shrink-0 text-crimson-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" /><path d="M16 8.5a3 3 0 1 0 0-6" /><path d="M17.5 14.3c2.3.6 4 2.7 4 5.7" /></svg>
                            <span class="flex-1 text-ink">{{ $row['applicant'] }} <span class="font-mono text-mist">{{ $row['student_id'] }}</span></span>
                            <span class="font-mono text-slate">{{ $row['awards'] }} grants</span>
                        </div>
                    @empty
                        <p class="font-body text-[14px] text-mist">No one holds more than one active grant this term.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-display text-[16px] font-600 text-ink">Most common disqualification reasons</p>
                <div class="mt-3 space-y-2.5">
                    @forelse ($disqualifications as $row)
                        <div class="flex items-center gap-2.5 font-body text-[14px]">
                            <svg class="size-4 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v5" /><path d="M12 16h.01" /></svg>
                            <span class="flex-1 text-ink">{{ $row['reason'] }}</span>
                            <span class="font-mono text-slate">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <p class="font-body text-[14px] text-mist">No disqualifications recorded this term.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
