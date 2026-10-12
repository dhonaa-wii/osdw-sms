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

@php
    $budget = $provider->allocated_budget !== null ? (float) $provider->allocated_budget : null;
    $usedPercent = $budget ? min(100, round($grandTotal / $budget * 100)) : null;
@endphp

<div>
    <x-ui.back-link :href="route('admin.providers')" label="Providers" />

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">{{ $provider->name }}</h1>
        <x-ui.active-stamp :active="$provider->is_active" />
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1.5 font-body text-[13px] text-slate">
        <span class="inline-flex items-center gap-1.5">
            <x-ui.icon name="layers" class="size-4 text-mist" />
            {{ $provider->funding_source->label() }}
        </span>
        @if ($provider->contact_person)
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="user" class="size-4 text-mist" />
                {{ $provider->contact_person }}
            </span>
        @endif
        @if ($provider->contact_email)
            <a href="mailto:{{ $provider->contact_email }}" class="inline-flex items-center gap-1.5 rounded-sm hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                <x-ui.icon name="mail" class="size-4 text-mist" />
                {{ $provider->contact_email }}
            </a>
        @endif
    </div>

    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 {{ $budget !== null ? 'lg:grid-cols-3' : '' }}">
        <div class="flex items-center gap-3 rounded-card border border-line bg-surface p-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                <x-ui.icon name="award" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="font-body text-[12px] text-slate">Total awarded</p>
                <p class="truncate font-mono text-[18px] leading-tight text-ink">₱{{ number_format($grandTotal, 2) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-card border border-line bg-surface p-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                <x-ui.icon name="users" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="font-body text-[12px] text-slate">Awards recorded</p>
                <p class="font-mono text-[18px] leading-tight text-ink">{{ $totalScholars }}</p>
            </div>
        </div>
        @if ($budget !== null)
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="font-body text-[12px] text-slate">Allocated budget</p>
                    <p class="font-mono text-[12px] text-slate">{{ $usedPercent }}% used</p>
                </div>
                <p class="truncate font-mono text-[18px] leading-tight text-ink">₱{{ number_format($budget, 2) }}</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-paper" role="progressbar" aria-valuenow="{{ $usedPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Budget used">
                    <div @class(['h-full rounded-full', 'bg-navy-600' => $grandTotal <= $budget, 'bg-crimson-600' => $grandTotal > $budget]) style="width: {{ $usedPercent }}%"></div>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 class="font-display text-[16px] font-600 text-ink">Scholarship programs</h2>
            <span class="font-mono text-[12px] text-mist">{{ $rows->count() }}</span>
        </div>

        @forelse ($rows as $row)
            <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" wire:key="provider-program-{{ $row['program']->id }}" class="border-b border-line last:border-b-0">
                <button
                    type="button"
                    @click="open = ! open"
                    :aria-expanded="open"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-paper/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-600"
                >
                    <x-ui.icon name="chevron-right" class="size-4 text-mist transition-transform" ::class="open && 'rotate-90'" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-body text-[14px] font-500 text-ink">{{ $row['program']->name }}</span>
                        <span class="block font-body text-[12px] text-mist">
                            {{ $row['scholars'] }} {{ Str::plural('award', $row['scholars']) }} · {{ $row['terms']->count() }} {{ Str::plural('term', $row['terms']->count()) }}
                        </span>
                    </span>
                    <span class="font-mono text-[14px] text-ink">₱{{ number_format($row['total'], 2) }}</span>
                </button>

                <div x-show="open" x-collapse>
                    <div class="px-4 pb-4 pl-11">
                        @if ($row['terms']->isEmpty())
                            <p class="font-body text-[13px] text-mist">
                                No awards recorded yet.
                                <a href="{{ route('admin.scholarship-programs.offerings', $row['program']) }}" wire:navigate class="font-500 text-navy-700 hover:underline">Open offerings</a>
                            </p>
                        @else
                            <div class="overflow-hidden rounded-control border border-line">
                                <table class="w-full font-body text-[13px]">
                                    <thead class="bg-paper/60">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Term</th>
                                            <th class="px-3 py-1.5 text-right text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Awards</th>
                                            <th class="px-3 py-1.5 text-right text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($row['terms'] as $termRow)
                                            <tr wire:key="program-{{ $row['program']->id }}-term-{{ $termRow['term']->id }}">
                                                <td class="px-3 py-2 text-ink">{{ $termRow['term']->school_year }} · {{ $termRow['term']->semester->label() }}</td>
                                                <td class="px-3 py-2 text-right font-mono text-ink">{{ $termRow['scholars'] }}</td>
                                                <td class="px-3 py-2 text-right font-mono text-ink">₱{{ number_format($termRow['amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                    <x-ui.icon name="award" class="size-6" />
                </span>
                <p class="mt-3 font-display text-[16px] font-600 text-ink">No scholarship programs yet</p>
                <p class="mt-1 font-body text-[14px] text-slate">Add a scholarship program under this provider.</p>
                <x-ui.button-link :href="route('admin.scholarship-programs')" class="mt-4" wire:navigate>Go to scholarship programs</x-ui.button-link>
            </div>
        @endforelse
    </div>
</div>
