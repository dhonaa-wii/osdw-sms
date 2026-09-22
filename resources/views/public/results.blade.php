<?php

use App\Models\Award;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public', array('title' => 'Published Results', 'noindex' => true))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $siteVisible = true;

    public function mount(): void
    {
        $setting = Setting::find('public_results_visible');
        $this->siteVisible = $setting === null || $setting->value === '1';
    }

    public function with(): array
    {
        if (! $this->siteVisible) {
            return ['awards' => collect()];
        }

        return [
            'awards' => Award::query()
                ->whereHas('programOffering', fn ($query) => $query->where('results_published', true))
                ->whereHas('application', fn ($query) => $query->where('consent_publish', true))
                ->when($this->search, fn ($query) => $query->whereHas(
                    'application.applicant',
                    fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                ))
                ->with(['application.applicant', 'application.college', 'programOffering.scholarshipProgram'])
                ->orderByDesc('awarded_at')
                ->paginate(15),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-[28px] font-600 text-ink">Published results</h1>
        </div>
        <a href="{{ route('track.lookup') }}" class="font-body text-[13px] text-slate transition-colors hover:text-forest-700">Don't see your name? Track your status &rarr;</a>
    </div>

    <div class="mt-6 max-w-sm">
        <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by name" />
    </div>

    <div class="mt-6">
        @if (! $siteVisible)
            <x-ui.empty-state title="This page is temporarily unavailable" message="Check back later." />
        @elseif ($awards->isEmpty())
            <x-ui.empty-state title="No results published yet" message="Check back once OSDW posts award results for a scholarship." />
        @else
            <div class="space-y-3">
                @foreach ($awards as $award)
                    <div class="flex items-start gap-3 rounded-card border border-line bg-surface p-4 transition hover:border-forest-600" wire:key="award-{{ $award->id }}">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" /></svg>
                        </span>
                        <div>
                            <p class="font-body text-[15px] text-ink">{{ $award->application->applicant->last_name }}, {{ $award->application->applicant->first_name }}</p>
                            <p class="mt-1 font-body text-[13px] text-slate">
                                {{ $award->programOffering->scholarshipProgram->name }}
                                &middot; {{ $award->application->college->name ?? 'N/A' }}
                                &middot; <span class="font-mono">{{ $award->awarded_at->format('M j, Y') }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($awards->hasPages())
                <div class="mt-6">{{ $awards->links() }}</div>
            @endif
        @endif
    </div>
</div>
