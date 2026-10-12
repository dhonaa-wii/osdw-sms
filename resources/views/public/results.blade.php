<?php

use App\Models\ProgramOffering;
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        if (! $this->siteVisible) {
            return ['offerings' => collect()];
        }

        return [
            'offerings' => ProgramOffering::query()
                ->where('results_published', true)
                ->when($this->search, fn ($query) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('scholarshipProgram', fn ($program) => $program->where('name', 'like', "%{$this->search}%"))
                ))
                ->with('scholarshipProgram.provider', 'academicTerm')
                ->withCount('awards as awardee_count')
                ->orderByDesc('results_published_at')
                ->paginate(15),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="font-display text-[24px] font-600 text-ink">Published results</h1>
        <a href="{{ auth()->user()?->isStudent() ? route('student.applications.index') : route('login') }}" wire:navigate class="inline-flex items-center gap-1 font-body text-[13px] text-slate transition-colors hover:text-navy-700">Don't see your name? Check your applications <x-ui.icon name="arrow-right" class="size-3.5" /></a>
    </div>

    @if (! $siteVisible)
        <div class="mt-4">
            <x-ui.empty-state title="This page is temporarily unavailable" message="Check back later." />
        </div>
    @else
        <x-ui.search name="search" label="Search published results" wire:model.live.debounce.300ms="search" placeholder="Search scholarship or offering" class="mt-4 sm:max-w-sm" />

        <div class="mt-4 transition-opacity" wire:loading.class="opacity-60" wire:target="search, gotoPage, nextPage, previousPage">
            @if ($offerings->isEmpty())
                @if ($search !== '')
                    <x-ui.empty-state title="No published results match your search" message="Try a different scholarship name, or clear the search." />
                @else
                    <x-ui.empty-state title="No results published yet" message="Check back once OSDW posts award results for a scholarship." />
                @endif
            @else
                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($offerings as $offering)
                        <li wire:key="offering-{{ $offering->id }}">
                            <a href="{{ route('programs.results', $offering) }}" wire:navigate class="group flex items-center gap-3 rounded-card border border-line bg-surface p-4 transition hover:border-navy-600 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600">
                                    <x-ui.icon name="award" class="size-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-display text-[15px] font-600 text-ink group-hover:text-navy-700">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</p>
                                    <p class="truncate font-body text-[12px] text-slate">{{ $offering->scholarshipProgram->provider->name }}</p>
                                    <p class="mt-1 flex items-center gap-3 font-body text-[12px] text-slate">
                                        <span class="inline-flex items-center gap-1"><x-ui.icon name="users" class="size-3.5 text-mist" /><span class="font-mono text-ink">{{ $offering->awardee_count }}</span> {{ $offering->awardee_count === 1 ? 'awardee' : 'awardees' }}</span>
                                        <span class="inline-flex items-center gap-1"><x-ui.icon name="calendar" class="size-3.5 text-mist" /><span class="font-mono">{{ $offering->results_published_at?->format('M j, Y') }}</span></span>
                                    </p>
                                </div>
                                <x-ui.icon name="chevron-right" class="size-4 text-mist transition-transform group-hover:translate-x-0.5 group-hover:text-navy-700" />
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($offerings->hasPages())
                    <div class="mt-6">{{ $offerings->links() }}</div>
                @endif
            @endif
        </div>
    @endif
</div>
