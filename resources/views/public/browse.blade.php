<?php

use App\Models\ProgramOffering;
use App\Models\Provider;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public', array('title' => 'Open Scholarships'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $provider_id = '';

    public string $category = '';

    public function with(): array
    {
        return [
            'offerings' => ProgramOffering::open()
                ->whereHas('scholarshipProgram', fn ($query) => $query->where('is_active', true))
                ->with('scholarshipProgram.provider', 'academicTerm')
                ->when($this->search, fn ($query) => $query->whereHas(
                    'scholarshipProgram',
                    fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ))
                ->when($this->provider_id, fn ($query) => $query->whereHas(
                    'scholarshipProgram',
                    fn ($q) => $q->where('provider_id', $this->provider_id)
                ))
                ->when($this->category, fn ($query) => $query->whereHas(
                    'scholarshipProgram',
                    fn ($q) => $q->where('category', $this->category)
                ))
                ->orderBy('closes_at')
                ->paginate(10),
            'providers' => Provider::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <h1 class="font-display text-[28px] font-600 text-ink">Open scholarships</h1>

    <div class="mt-6 rounded-card border border-line bg-surface p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by name" />
            <x-ui.select name="provider_id" wire:model.live="provider_id" placeholder="Any provider">
                @foreach ($providers as $provider)
                    <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="category" wire:model.live="category" placeholder="Any type">
                @foreach (\App\Enums\ScholarshipCategory::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
    </div>

    <p class="mt-4 font-body text-[13px] text-slate">{{ $offerings->total() }} scholarship{{ $offerings->total() === 1 ? '' : 's' }} open</p>

    <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($offerings as $offering)
            @php $closingSoon = $offering->closes_at <= now()->addDays(7); @endphp
            <a href="{{ route('programs.show', $offering) }}" class="group flex h-full flex-col rounded-card border border-line bg-surface p-5 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <p class="font-body text-[12px] text-slate">{{ $offering->scholarshipProgram->category->label() }}</p>
                <p class="mt-0.5 font-display text-[17px] font-600 text-ink">{{ $offering->scholarshipProgram->name }}</p>
                <p class="mt-1 font-body text-[13px] text-slate">{{ $offering->scholarshipProgram->provider->name }} &middot; {{ $offering->displayName() }}</p>

                @if ($closingSoon)
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
                        <span class="border-l-2 border-amber-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Closing soon</span>
                    </div>
                @endif

                <div class="mt-auto flex flex-wrap gap-x-4 gap-y-1 border-t border-line pt-3 mt-4 font-body text-[13px] text-slate">
                    <span>Slots: <span class="font-mono text-ink">{{ $offering->total_slots }}</span></span>
                    <span>Deadline: <span class="font-mono text-ink">{{ $offering->closes_at->format('M j, Y') }}</span></span>
                    @if ($offering->amount)
                        <span>Amount: <span class="font-mono text-ink">&#8369;{{ number_format($offering->amount, 2) }}</span></span>
                    @elseif ($offering->discount_rate)
                        <span>Tuition discount: <span class="font-mono text-ink">{{ $offering->discount_rate }}%</span></span>
                    @endif
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-ui.empty-state title="No open scholarships right now" message="Check back once OSDW opens a new application window.">
                    <x-slot:action>
                        <a href="{{ route('track.lookup') }}" class="font-body text-[13px] font-500 text-forest-700 hover:underline">Already applied? Track your application &rarr;</a>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endforelse
    </div>

    @if ($offerings->hasPages())
        <div class="mt-6">{{ $offerings->links() }}</div>
    @endif
</div>
