<?php

use App\Models\Provider;
use App\Services\ProviderService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Provider'))] class extends Component
{
    public Provider $provider;

    public function mount(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function with(ProviderService $providerService): array
    {
        return $providerService->fundingSummary($this->provider);
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.providers')" label="Providers" />

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="font-display text-[30px] font-600 text-ink">{{ $provider->name }}</h1>
        <x-ui.active-stamp :active="$provider->is_active" />
    </div>

    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 font-body text-[14px] text-slate">
        <span>{{ $provider->funding_source->label() }}</span>
        @if ($provider->contact_person)
            <span>{{ $provider->contact_person }}</span>
        @endif
        @if ($provider->contact_email)
            <span>{{ $provider->contact_email }}</span>
        @endif
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $provider->allocated_budget !== null ? 'lg:grid-cols-3' : '' }}">
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Total provided, all time</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">&#8369;{{ number_format($grandTotal, 2) }}</p>
            <p class="mt-1 font-body text-[12px] text-slate">Sum of active and completed awards across every program this provider funds.</p>
        </div>
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Scholars funded, all time</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">{{ $totalScholars }}</p>
            <p class="mt-1 font-body text-[12px] text-slate">Counts awards that were accepted, not just reserved.</p>
        </div>
        @if ($provider->allocated_budget !== null)
            <div class="rounded-card border border-line bg-surface p-4">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Allocated budget</p>
                <p class="mt-1 font-mono text-[20px] leading-tight text-ink">&#8369;{{ number_format($provider->allocated_budget, 2) }}</p>
                <p class="mt-1 font-body text-[12px] text-slate">&#8369;{{ number_format($grandTotal, 2) }} provided against this budget.</p>
            </div>
        @endif
    </div>

    <h2 class="mt-8 font-display text-[20px] font-600 text-ink">Scholarship programs</h2>

    <div class="mt-4 space-y-6">
        @forelse ($rows as $row)
            <div class="rounded-card border border-line bg-paper p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-body text-[15px] font-500 text-ink">{{ $row['program']->name }}</p>
                    </div>
                    <p class="font-mono text-[16px] text-ink">&#8369;{{ number_format($row['total'], 2) }}</p>
                </div>

                @if ($row['terms']->isEmpty())
                    <p class="mt-3 font-body text-[13px] text-mist">No awards recorded for this program yet.</p>
                @else
                    <div class="mt-4">
                        <x-ui.data-table :headers="[
                            ['key' => 'term', 'label' => 'Term', 'sortable' => false],
                            ['key' => 'scholars', 'label' => 'Scholars', 'sortable' => false],
                            ['key' => 'amount', 'label' => 'Amount', 'sortable' => false],
                        ]" :rows="null">
                            @foreach ($row['terms'] as $termRow)
                                <tr wire:key="program-{{ $row['program']->id }}-term-{{ $termRow['term']->id }}" class="transition hover:bg-paper">
                                    <td data-label="Term" class="text-[14px] text-ink">{{ $termRow['term']->school_year }} &middot; {{ $termRow['term']->semester->label() }}</td>
                                    <td data-label="Scholars" class="font-mono text-[14px] text-ink">{{ $termRow['scholars'] }}</td>
                                    <td data-label="Amount" class="font-mono text-[14px] text-ink">&#8369;{{ number_format($termRow['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </x-ui.data-table>
                    </div>
                @endif
            </div>
        @empty
            <x-ui.empty-state title="No scholarship programs yet" message="Programs assigned to this provider will appear here." />
        @endforelse
    </div>
</div>
