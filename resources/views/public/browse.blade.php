<?php

use App\Enums\OfferingStatus;
use App\Models\ProgramOffering;
use App\Models\Provider;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public', array('title' => 'Scholarship Programs'))] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'provider', except: '')]
    public string $provider_id = '';

    #[Url(as: 'type', except: '')]
    public string $category = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'provider_id', 'category'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'provider_id', 'category');
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            // Only offerings taking applications right now; closed and upcoming ones stay off the public list.
            'offerings' => ProgramOffering::open()
                ->whereHas('scholarshipProgram', fn ($query) => $query->where('is_active', true))
                ->with('scholarshipProgram.provider', 'academicTerm', 'renewsOffering')
                ->when($this->search, fn ($query) => $query->whereHas(
                    'scholarshipProgram',
                    fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ))
                ->when($this->provider_id, fn ($query) => $query->whereHas(
                    'scholarshipProgram',
                    fn ($q) => $q->where('provider_id', $this->provider_id)
                ))
                ->when($this->category, fn ($query) => $query->where('category', $this->category))
                ->orderBy('closes_at')
                ->paginate(12),
            'providers' => Provider::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

@php
    $filtered = $search !== '' || $provider_id !== '' || $category !== '';
    $selectClass = 'h-10 w-full appearance-none rounded-control border border-line bg-surface pl-3 pr-9 font-body text-[14px] text-ink transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25 sm:w-44';
@endphp

<div>
    <x-ui.back-link :href="route('home')" wire:navigate />

    <div class="mt-4 flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="font-display text-[24px] font-600 text-ink">Scholarship programs</h1>
        <p class="font-body text-[13px] text-slate">
            <span class="font-mono text-ink">{{ $offerings->total() }}</span> {{ $offerings->total() === 1 ? 'scholarship' : 'scholarships' }}
        </p>
    </div>

    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
        <x-ui.search name="search" label="Search scholarships" wire:model.live.debounce.300ms="search" placeholder="Search scholarships" class="flex-1" />

        <div class="grid grid-cols-2 gap-2 sm:flex">
            <div class="relative">
                <label for="provider_id" class="sr-only">Provider</label>
                <select id="provider_id" wire:model.live="provider_id" class="{{ $selectClass }}">
                    <option value="">All providers</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                    @endforeach
                </select>
                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
            </div>
            <div class="relative">
                <label for="category" class="sr-only">Type of scholarship</label>
                <select id="category" wire:model.live="category" class="{{ $selectClass }}">
                    <option value="">All types</option>
                    @foreach (\App\Enums\ScholarshipCategory::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
            </div>
        </div>
    </div>

    <div class="mt-4 transition-opacity" wire:loading.class="opacity-60" wire:target="search, provider_id, category, clearFilters, gotoPage, nextPage, previousPage">
        @if ($offerings->isEmpty())
            <x-ui.empty-state
                :title="$filtered ? 'No scholarships match your search' : 'No scholarships are open right now'"
                :message="$filtered ? 'Try a different name, provider, or type.' : 'Check back soon. Students with an account are notified when one opens.'"
            >
                @if ($filtered)
                    <x-slot:action>
                        <x-ui.button variant="secondary" wire:click="clearFilters" loading="clearFilters">Clear filters</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offerings as $offering)
                    @php
                        $status = $offering->registrationStatus();
                        $closingSoon = $status === OfferingStatus::Open && $offering->closes_at <= now()->addDays(7);
                    @endphp
                    <a
                        href="{{ route('programs.show', $offering) }}"
                        wire:navigate
                        wire:key="offering-{{ $offering->id }}"
                        class="group flex flex-col rounded-card border border-line bg-surface p-4 transition hover:border-navy-600 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate font-body text-[12px] text-slate">{{ $offering->category->label() }}</span>
                            <x-ui.status-stamp :status="$status" class="shrink-0" />
                        </div>

                        <p class="mt-2 line-clamp-2 font-display text-[16px] font-600 leading-snug text-ink group-hover:text-navy-700">{{ $offering->scholarshipProgram->name }}</p>
                        <p class="mb-4 mt-0.5 truncate font-body text-[13px] text-slate">{{ $offering->scholarshipProgram->provider->name }} &middot; {{ $offering->displayName() }}</p>

                        <div class="mt-auto flex items-center gap-4 border-t border-line pt-3 font-body text-[12px] text-slate">
                            <span class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="calendar" class="size-3.5 text-mist" />
                                <span class="font-mono text-ink">{{ $offering->closes_at->format('M j, Y') }}</span>
                            </span>
                            <span class="inline-flex min-w-0 items-center gap-1.5">
                                <x-ui.icon name="users" class="size-3.5 text-mist" />
                                @if ($offering->takesNewApplicants())
                                    <span><span class="font-mono text-ink">{{ $offering->total_slots }}</span> slots</span>
                                @else
                                    <span class="truncate">Renewing scholars</span>
                                @endif
                            </span>
                            @if ($closingSoon)
                                <span class="ml-auto shrink-0 border-l-2 border-amber-600 pl-2 text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Closing soon</span>
                            @else
                                <x-ui.icon name="arrow-right" class="ml-auto size-4 text-mist transition-transform group-hover:translate-x-0.5 group-hover:text-navy-700" />
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($offerings->hasPages())
                <div class="mt-6">{{ $offerings->links() }}</div>
            @endif
        @endif
    </div>
</div>
