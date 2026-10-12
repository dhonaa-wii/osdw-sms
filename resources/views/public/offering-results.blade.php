<?php

use App\Models\Award;
use App\Models\ProgramOffering;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Awarded Applicants', 'noindex' => true))] class extends Component
{
    public ProgramOffering $offering;

    public string $search = '';

    public function mount(ProgramOffering $offering): void
    {
        $setting = Setting::find('public_results_visible');
        abort_unless($setting === null || $setting->value === '1', 404);
        abort_unless($offering->results_published, 404);

        $this->offering = $offering->load('scholarshipProgram.provider', 'academicTerm');
    }

    public function with(): array
    {
        return [
            'awardees' => Award::where('program_offering_id', $this->offering->id)
                ->whereHas('application', fn ($query) => $query->where('consent_publish', true))
                ->when($this->search, fn ($query) => $query->whereHas(
                    'applicant',
                    fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                ))
                ->with('applicant', 'application.college')
                ->get()
                ->sortBy(fn ($award) => $award->applicant->last_name.' '.$award->applicant->first_name)
                ->values(),
            'totalAwarded' => Award::where('program_offering_id', $this->offering->id)->count(),
            // Awardees who did not consent are counted, never named.
            'unlistedCount' => Award::where('program_offering_id', $this->offering->id)
                ->whereHas('application', fn ($query) => $query->where('consent_publish', false))
                ->count(),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('results.index')" label="Published results" wire:navigate />

    <div class="mt-3 flex items-center gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600">
            <x-ui.icon name="award" class="size-5" />
        </span>
        <div class="min-w-0">
            <h1 class="font-display text-[22px] font-600 leading-snug text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }} awardees</h1>
            <p class="font-body text-[12px] text-slate">{{ $offering->scholarshipProgram->provider->name }} &middot; Posted <span class="font-mono">{{ $offering->results_published_at?->format('M j, Y') }}</span></p>
        </div>
    </div>

    @if ($offering->publish_instructions)
        <section class="mt-4 rounded-card border border-line bg-surface" aria-labelledby="instructions-heading">
            <h2 id="instructions-heading" class="flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink">
                <x-ui.icon name="info" class="size-4 text-navy-600" /> Instructions
            </h2>
            <p class="whitespace-pre-line px-5 py-4 font-body text-[14px] leading-relaxed text-ink">{{ $offering->publish_instructions }}</p>
        </section>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <x-ui.search name="search" label="Find your name" wire:model.live.debounce.300ms="search" placeholder="Find your name" class="w-full sm:max-w-sm" />
        <p class="font-body text-[13px] text-slate">
            @if ($search !== '')
                <span class="font-mono text-ink">{{ $awardees->count() }}</span> match{{ $awardees->count() === 1 ? '' : 'es' }}
            @else
                <span class="font-mono text-ink">{{ $totalAwarded }}</span> awardee{{ $totalAwarded === 1 ? '' : 's' }}
            @endif
        </p>
    </div>

    <div class="mt-3 transition-opacity" wire:loading.class="opacity-60" wire:target="search">
        @if ($awardees->isEmpty())
            @if ($search !== '')
                <x-ui.empty-state title="No awardee matches that name" message="Check the spelling, or find your application to see its status." />
            @else
                <x-ui.empty-state title="No names to show" :message="'All '.$totalAwarded.' awardees chose not to have their names published. Log in to see your own status.'" />
            @endif
        @else
            <x-ui.data-table dense :headers="[
                ['key' => 'seq', 'label' => '#'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'college', 'label' => 'College'],
            ]">
                @foreach ($awardees as $award)
                    <tr wire:key="awardee-{{ $award->id }}">
                        <td data-label="#" class="w-12 font-mono text-[13px] text-slate">{{ $loop->iteration }}</td>
                        <td data-label="Name" class="font-body text-[14px] text-ink">{{ $award->applicant->last_name }}, {{ $award->applicant->first_name }}</td>
                        <td data-label="College" class="font-body text-[14px] text-slate">{{ $award->application->college->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif

        @if ($unlistedCount > 0 && $awardees->isNotEmpty() && $search === '')
            <p class="mt-3 flex items-start gap-2 rounded-control border border-line bg-surface px-4 py-3 font-body text-[13px] text-slate">
                <x-ui.icon name="lock" class="mt-0.5 size-4 text-mist" />
                <span>{{ $unlistedCount }} more {{ $unlistedCount === 1 ? 'awardee' : 'awardees' }} chose not to have {{ $unlistedCount === 1 ? 'their name' : 'their names' }} published.</span>
            </p>
        @endif
    </div>

    <p class="mt-4 font-body text-[13px] text-slate">
        Applied to this scholarship?
        @if (auth()->user()?->isStudent())
            <a href="{{ route('student.applications.index') }}" wire:navigate class="font-500 text-navy-700 hover:underline">See My applications</a> for your status.
        @else
            <a href="{{ route('login') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Log in</a> to see your status.
        @endif
    </p>
</div>
