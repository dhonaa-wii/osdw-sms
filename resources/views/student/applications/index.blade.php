<?php

use App\Enums\StudentApplicationFilter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'My applications', 'noindex' => true))] class extends Component
{
    #[Url(as: 'status', except: 'all')]
    public string $filter = 'all';

    public function with(): array
    {
        $applications = auth()->user()->applicant->applications()
            ->with('programOffering.scholarshipProgram.provider', 'programOffering.academicTerm', 'award')
            ->latest('submitted_at')
            ->latest('id')
            ->get();

        $filter = StudentApplicationFilter::tryFrom($this->filter);

        $counts = ['all' => $applications->count()];
        foreach (StudentApplicationFilter::cases() as $case) {
            $counts[$case->value] = $applications->filter(fn ($application) => in_array($application->status, $case->statuses(), true))->count();
        }

        return [
            'applications' => $filter
                ? $applications->filter(fn ($application) => in_array($application->status, $filter->statuses(), true))->values()
                : $applications,
            'counts' => $counts,
            'options' => ['all' => 'All'] + collect(StudentApplicationFilter::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all(),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="font-display text-[24px] font-600 text-ink">My applications</h1>
        <a href="{{ route('programs.index') }}" wire:navigate class="inline-flex items-center gap-1 font-body text-[13px] font-500 text-navy-700 hover:underline">Browse open scholarships <x-ui.icon name="arrow-right" class="size-3.5" /></a>
    </div>

    <section class="mt-4 overflow-hidden rounded-card border border-line bg-surface">
        <div class="overflow-x-auto border-b border-line p-3">
            <x-ui.segmented :options="$options" :counts="$counts" model="filter" :value="$filter" label="Filter by status" class="min-w-max sm:w-fit" />
        </div>

        <div class="transition-opacity" wire:loading.class="opacity-60" wire:target="filter">
            @if ($applications->isEmpty())
                <div class="p-5">
                    @if ($counts['all'] === 0)
                        <x-ui.empty-state title="You haven't applied yet" message="Find a scholarship that's open and apply in one sitting.">
                            <x-slot:action>
                                <x-ui.button-link :href="route('programs.index')" wire:navigate>Browse scholarships</x-ui.button-link>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state title="Nothing here" message="None of your applications are in this group.">
                            <x-slot:action>
                                <x-ui.button variant="secondary" wire:click="$set('filter', 'all')">Show all applications</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @endif
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($applications as $application)
                        @php $offering = $application->programOffering; @endphp
                        <li wire:key="application-{{ $application->id }}">
                            <a
                                href="{{ route('student.applications.show', $application) }}"
                                wire:navigate
                                class="group flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-paper focus-visible:bg-paper focus-visible:outline-none sm:px-5"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $application->award ? 'bg-gold-50 text-gold-600' : 'bg-paper text-slate group-hover:bg-surface' }}">
                                    <x-ui.icon :name="$application->award ? 'award' : ($application->isRenewal() ? 'layers' : 'form')" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-body text-[14px] font-600 text-ink group-hover:text-navy-700">{{ $offering->scholarshipProgram->name }}</span>
                                    <span class="block truncate font-body text-[12px] text-slate">
                                        {{ $offering->displayName() }} &middot; {{ $application->kind->label() }} &middot; <span class="font-mono">{{ $application->submitted_at?->format('M j, Y') }}</span>
                                    </span>
                                </span>
                                <x-ui.status-stamp :application="$application" class="hidden shrink-0 sm:inline" />
                                <x-ui.icon name="chevron-right" class="size-4 shrink-0 text-mist group-hover:text-navy-700" />
                            </a>
                            <div class="-mt-2 pb-3 pl-16 sm:hidden">
                                <x-ui.status-stamp :application="$application" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
