<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\College;
use App\Models\Program;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProviderService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Dashboard'))] class extends Component
{
    public const RANGES = [7, 14, 30];

    public string $search = '';

    public int $days = 14;

    public function updatedDays(): void
    {
        if (! in_array($this->days, self::RANGES, true)) {
            $this->days = 14;
        }
    }

    protected function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Gridlines for the activity chart: four even steps up to a round top
     * value, so the axis reads 0 / 5 / 10 / 15 / 20 rather than odd numbers.
     */
    protected function chartScale(int $max): int
    {
        $step = max(1, (int) ceil($max / 4));

        return $step * 4;
    }

    public function with(DashboardService $dashboard): array
    {
        $isAdmin = auth()->user()->role === UserRole::Admin;
        $term = $dashboard->currentTerm();
        $activity = $dashboard->dailyActivity($this->days);

        $data = [
            'isAdmin' => $isAdmin,
            'ranges' => self::RANGES,
            'greeting' => $this->greeting(),
            'currentTerm' => $term,
            'results' => $dashboard->search($this->search),
            'funnel' => $dashboard->funnel($term),
            'statusBreakdown' => $dashboard->statusBreakdown($term),
            'slotUsage' => $dashboard->slotUtilization($term),
            'openOfferings' => $dashboard->openOfferings(),
            'tasks' => $dashboard->pendingTasks(),
            'recentAwards' => $dashboard->recentAwards(),
            'activity' => $activity,
            'activityTop' => $this->chartScale((int) $activity->max(fn ($day) => max($day['received'], $day['screened']))),
            'activityTotals' => ['received' => $activity->sum('received'), 'screened' => $activity->sum('screened')],
        ];

        if (! $isAdmin) {
            return $data;
        }

        return $data + [
            'budgetUtilization' => app(ProviderService::class)->budgetUtilization(),
            'termTrends' => $dashboard->termTrends(),
            'records' => [
                ['label' => 'Colleges', 'value' => College::where('is_active', true)->count(), 'total' => College::count(), 'route' => 'admin.colleges'],
                ['label' => 'Programs', 'value' => Program::count(), 'total' => null, 'route' => 'admin.programs'],
                ['label' => 'Providers', 'value' => Provider::where('is_active', true)->count(), 'total' => Provider::count(), 'route' => 'admin.providers'],
                ['label' => 'Scholarship programs', 'value' => ScholarshipProgram::where('is_active', true)->count(), 'total' => ScholarshipProgram::count(), 'route' => 'admin.scholarship-programs'],
                ['label' => 'Academic terms', 'value' => AcademicTerm::count(), 'total' => null, 'route' => 'admin.academic-terms'],
                ['label' => 'Staff accounts', 'value' => User::staff()->count(), 'total' => null, 'route' => 'admin.users'],
            ],
        ];
    }
};
?>

@php
    $card = 'rounded-card border border-line bg-surface p-3.5 sm:p-4';
    $cardTitle = 'font-display text-[13px] font-600 text-ink sm:text-[14px]';
    $percentOf = fn (int $part, int $whole) => $whole > 0 ? (int) min(100, round($part / $whole * 100)) : 0;
    $reviewUrl = fn ($offering) => $offering->isRenewal() ? route('staff.renewals.index', $offering) : route('staff.applications.workspace', $offering);
    $initials = fn (string $name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp

<div class="space-y-3 sm:space-y-4">
    {{-- Header: greeting, search, signed-in user --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1">
            <h1 class="font-display text-[17px] font-600 text-ink sm:text-[19px] lg:text-[20px]">{{ $greeting }}, {{ str(auth()->user()->name)->before(' ') }}</h1>
            @if ($currentTerm)
                <span class="rounded-full bg-navy-50 px-2 py-0.5 font-body text-[11px] font-500 text-navy-700 sm:text-[12px]">{{ $currentTerm->school_year }} &middot; {{ $currentTerm->semester->label() }} Semester</span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <div
                class="relative w-full sm:w-[17rem]"
                x-data="{ open: false }"
                @click.outside="open = false"
                @keydown.escape="open = false; $refs.search.blur()"
                @keydown.window.ctrl.k.prevent="$refs.search.focus()"
                @keydown.window.meta.k.prevent="$refs.search.focus()"
            >
                <label for="dashboard-search" class="sr-only">Search programs and providers</label>
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                <input
                    id="dashboard-search"
                    x-ref="search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    @focus="open = true"
                    @input="open = true"
                    placeholder="Search programs, providers"
                    autocomplete="off"
                    class="h-9 w-full rounded-full border border-line bg-surface pl-9 pr-12 font-body text-[12px] text-ink shadow-sm transition placeholder:text-mist hover:border-navy-100 focus:border-navy-600 focus:outline-none focus:ring-4 focus:ring-navy-600/10"
                >
                <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center">
                    <svg wire:loading wire:target="search" class="size-4 animate-spin text-mist" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" opacity="0.25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    <span wire:loading.remove wire:target="search">
                        @if ($search !== '')
                            <button type="button" wire:click="$set('search', '')" class="flex size-6 items-center justify-center rounded-full text-mist hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-navy-600" aria-label="Clear search">
                                <x-ui.icon name="x" class="size-3.5" />
                            </button>
                        @else
                            <kbd class="hidden rounded-md border border-line bg-paper px-1.5 py-0.5 font-mono text-[9px] text-mist sm:inline-block">Ctrl K</kbd>
                        @endif
                    </span>
                </div>

                @if (mb_strlen(trim($search)) >= 2)
                    <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 z-20 mt-2 max-h-[70vh] w-full overflow-y-auto rounded-card border border-line bg-surface p-1.5 shadow-lg sm:w-80">
                        @if ($results['programs']->isEmpty() && $results['providers']->isEmpty())
                            <p class="px-2.5 py-3 font-body text-[13px] text-slate">No programs or providers match &ldquo;{{ trim($search) }}&rdquo;. Check the spelling or try the provider's name.</p>
                        @endif

                        @if ($results['programs']->isNotEmpty())
                            <p class="px-2.5 pb-1 pt-1.5 font-body text-[11px] font-600 text-mist">Scholarship programs</p>
                            @foreach ($results['programs'] as $row)
                                @php
                                    $url = $isAdmin
                                        ? route('admin.scholarship-programs.offerings', $row['program'])
                                        : ($row['latest'] ? $reviewUrl($row['latest']) : null);
                                @endphp
                                <a @if ($url) href="{{ $url }}" @endif wire:key="search-program-{{ $row['program']->id }}" class="flex items-center gap-2.5 rounded-control px-2.5 py-2 {{ $url ? 'hover:bg-paper focus-visible:outline-2 focus-visible:outline-navy-600' : '' }}">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-700"><x-ui.icon name="award" class="size-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-body text-[13px] text-ink">{{ $row['program']->name }}</span>
                                        <span class="block truncate font-body text-[11px] text-mist">{{ $row['program']->provider?->name }}@if ($row['latest']) &middot; {{ $row['latest']->displayName() }}@endif</span>
                                    </span>
                                    @unless ($row['program']->is_active)
                                        <span class="shrink-0 font-body text-[11px] text-mist">Inactive</span>
                                    @endunless
                                </a>
                            @endforeach
                        @endif

                        @if ($results['providers']->isNotEmpty())
                            <p class="px-2.5 pb-1 pt-2 font-body text-[11px] font-600 text-mist">Providers</p>
                            @foreach ($results['providers'] as $provider)
                                @php $url = $isAdmin ? route('admin.providers.view', $provider) : null; @endphp
                                <a @if ($url) href="{{ $url }}" @endif wire:key="search-provider-{{ $provider->id }}" class="flex items-center gap-2.5 rounded-control px-2.5 py-2 {{ $url ? 'hover:bg-paper focus-visible:outline-2 focus-visible:outline-navy-600' : '' }}">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-paper text-slate"><x-ui.icon name="building" class="size-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-body text-[13px] text-ink">{{ $provider->name }}</span>
                                        <span class="block truncate font-body text-[11px] text-mist">{{ $provider->scholarship_programs_count }} {{ str('program')->plural($provider->scholarship_programs_count) }}</span>
                                    </span>
                                </a>
                            @endforeach
                        @endif
                    </div>
                @endif
            </div>

            <div class="hidden shrink-0 items-center gap-2 rounded-control border border-line bg-surface py-1 pl-1 pr-2.5 sm:flex">
                <span class="flex size-7 items-center justify-center rounded-full bg-navy-600 font-display text-[11px] font-600 text-white">{{ $initials(auth()->user()->name) }}</span>
                <span class="leading-tight">
                    <span class="block max-w-[140px] truncate font-body text-[12px] font-500 text-ink">{{ auth()->user()->name }}</span>
                    <span class="block font-body text-[11px] text-mist">{{ auth()->user()->role->label() }}</span>
                </span>
            </div>
        </div>
    </div>

    @unless ($currentTerm)
        <x-ui.alert type="warning">No academic term is marked current &mdash; set one in Application Settings for accurate figures here.</x-ui.alert>
    @endunless

    <div class="grid grid-cols-1 gap-3 sm:gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
        {{-- Main column --}}
        <div class="min-w-0 space-y-3 sm:space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-[minmax(0,1fr)_200px]">
                {{-- Activity --}}
                <section class="{{ $card }} min-w-0">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="{{ $cardTitle }}">Application activity</h2>
                            <p class="mt-1 flex items-baseline gap-2">
                                <span class="font-mono text-[20px] leading-tight text-ink sm:text-[22px]">{{ number_format($activityTotals['received']) }}</span>
                                <span class="font-body text-[11px] text-mist sm:text-[12px]">received &middot; <span class="font-mono text-slate">{{ number_format($activityTotals['screened']) }}</span> screened</span>
                            </p>
                        </div>
                        <div class="inline-flex rounded-full border border-line bg-paper p-0.5" role="group" aria-label="Activity range">
                            @foreach ($ranges as $range)
                                <button
                                    type="button"
                                    wire:click="$set('days', {{ $range }})"
                                    aria-pressed="{{ $days === $range ? 'true' : 'false' }}"
                                    class="rounded-full px-2.5 py-1 font-body text-[11px] font-500 transition focus-visible:outline-2 focus-visible:outline-navy-600 sm:text-[12px] {{ $days === $range ? 'bg-surface text-navy-700 shadow-sm' : 'text-slate hover:text-ink' }}"
                                >{{ $range }}d</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-2 flex items-center gap-3">
                        <span class="flex items-center gap-1.5 font-body text-[11px] text-slate"><span class="size-2 rounded-sm bg-navy-600"></span>Received</span>
                        <span class="flex items-center gap-1.5 font-body text-[11px] text-slate"><span class="size-2 rounded-sm bg-azure-600/45"></span>Screened</span>
                    </div>

                    @php
                        $labelEvery = match (true) { $days <= 7 => 1, $days <= 14 => 2, default => 5 };
                    @endphp
                    <div class="relative mt-3 transition-opacity" wire:loading.class="opacity-50" wire:target="days">
                        <div class="flex">
                            <div class="relative mr-2 h-[130px] w-6 shrink-0 font-mono text-[10px] text-mist sm:h-[160px]" aria-hidden="true">
                                @foreach ([4, 3, 2, 1, 0] as $step)
                                    <span class="absolute right-0 -translate-y-1/2" style="top: {{ (4 - $step) * 25 }}%">{{ $activityTop / 4 * $step }}</span>
                                @endforeach
                            </div>
                            <div class="relative h-[130px] flex-1 sm:h-[160px]">
                                @foreach ([0, 25, 50, 75, 100] as $line)
                                    <div class="absolute inset-x-0 border-t {{ $line === 100 ? 'border-line' : 'border-dashed border-line/70' }}" style="top: {{ $line }}%" aria-hidden="true"></div>
                                @endforeach
                                <div class="absolute inset-0 flex items-end {{ $days > 14 ? 'gap-0.5' : 'gap-1 sm:gap-2' }}">
                                    @foreach ($activity as $day)
                                        <div
                                            wire:key="day-{{ $day['date']->format('Ymd') }}"
                                            class="group relative flex h-full flex-1 items-end justify-center gap-px rounded-sm hover:bg-navy-50/60 sm:gap-0.5"
                                            role="img"
                                            aria-label="{{ $day['date']->format('M j') }}: {{ $day['received'] }} received, {{ $day['screened'] }} screened"
                                        >
                                            <div class="w-full max-w-[14px] rounded-t-sm bg-navy-600 transition group-hover:bg-navy-700" style="height: {{ $percentOf($day['received'], $activityTop) }}%"></div>
                                            <div class="w-full max-w-[14px] rounded-t-sm bg-azure-600/45 transition group-hover:bg-azure-600/70" style="height: {{ $percentOf($day['screened'], $activityTop) }}%"></div>

                                            <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden w-max rounded-control border border-line bg-surface px-2.5 py-1.5 shadow-md group-hover:block">
                                                <p class="font-body text-[11px] text-mist">{{ $day['date']->format('D, M j') }}</p>
                                                <p class="flex items-center gap-1.5 font-body text-[11px] text-slate"><span class="size-1.5 rounded-full bg-navy-600"></span>Received <span class="ml-auto pl-3 font-mono text-ink">{{ $day['received'] }}</span></p>
                                                <p class="flex items-center gap-1.5 font-body text-[11px] text-slate"><span class="size-1.5 rounded-full bg-azure-600"></span>Screened <span class="ml-auto pl-3 font-mono text-ink">{{ $day['screened'] }}</span></p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        {{-- Labels sit centred under their day and never widen the card. --}}
                        <div class="relative ml-8 mt-1.5 h-4 overflow-hidden" aria-hidden="true">
                            @foreach ($activity as $i => $day)
                                @if ($i % $labelEvery === 0)
                                    <span
                                        class="absolute top-0 -translate-x-1/2 whitespace-nowrap font-body text-[10px] text-mist sm:text-[11px] {{ $i % ($labelEvery * 2) === 0 ? '' : 'max-sm:hidden' }} {{ $i === 0 ? 'translate-x-0' : '' }}"
                                        style="left: {{ $i === 0 ? 0 : ($i + 0.5) / $activity->count() * 100 }}%"
                                    >{{ $days <= 7 ? $day['date']->format('D') : $day['date']->format('M j') }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Funnel --}}
                <section class="{{ $card }} grid grid-cols-2 gap-x-3 gap-y-3 sm:grid-cols-4 lg:grid-cols-1 lg:content-between lg:gap-0 lg:divide-y lg:divide-line lg:py-1">
                    <h2 class="sr-only">This term</h2>
                    @foreach ([
                        ['label' => 'Received', 'value' => $funnel['received'], 'of' => null, 'icon' => 'form', 'tone' => 'bg-navy-50 text-navy-700'],
                        ['label' => 'Screened', 'value' => $funnel['screened'], 'of' => $funnel['received'], 'icon' => 'clipboard-check', 'tone' => 'bg-navy-50 text-navy-700'],
                        ['label' => 'Shortlisted', 'value' => $funnel['shortlisted'], 'of' => $funnel['received'], 'icon' => 'shortlist', 'tone' => 'bg-navy-50 text-navy-700'],
                        ['label' => 'Awarded', 'value' => $funnel['awarded'], 'of' => $funnel['received'], 'icon' => 'award', 'tone' => 'bg-gold-50 text-gold-600'],
                    ] as $stat)
                        <div class="flex items-center gap-2.5 lg:py-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $stat['tone'] }}"><x-ui.icon :name="$stat['icon']" class="size-4" /></span>
                            <div class="min-w-0">
                                <p class="truncate font-body text-[11px] text-mist sm:text-[12px]">{{ $stat['label'] }}</p>
                                <p class="font-mono text-[16px] leading-tight text-ink sm:text-[18px]">
                                    {{ number_format($stat['value']) }}
                                    @if ($stat['of'] !== null)
                                        <span class="text-[11px] text-mist">{{ $percentOf($stat['value'], $stat['of']) }}%</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </section>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                {{-- Status breakdown --}}
                @php $statusTotal = collect($statusBreakdown)->sum('value'); @endphp
                <section class="{{ $card }}">
                    <h2 class="{{ $cardTitle }}">Application status</h2>
                    <p class="mt-2 font-mono text-[18px] leading-tight text-ink sm:text-[20px]">{{ number_format($statusTotal) }}</p>
                    @if ($statusTotal === 0)
                        <p class="mt-2 font-body text-[12px] text-mist sm:text-[13px]">No applications this term yet.</p>
                    @else
                        <div class="mt-3 flex h-2.5 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="Application status breakdown">
                            @foreach ($statusBreakdown as $segment)
                                @if ($segment['value'] > 0)
                                    <div class="{{ $segment['bar'] }}" style="width: {{ $segment['value'] / $statusTotal * 100 }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($statusBreakdown as $segment)
                                <li class="flex items-center gap-2 font-body text-[12px] sm:text-[13px]">
                                    <span class="size-2 shrink-0 rounded-sm {{ $segment['bar'] }}"></span>
                                    <span class="text-slate">{{ $segment['label'] }}</span>
                                    <span class="ml-auto font-mono text-ink">{{ $segment['value'] }}</span>
                                    <span class="w-9 text-right font-mono text-[11px] text-mist">{{ $percentOf($segment['value'], $statusTotal) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- Slot utilization --}}
                @php $slotPercent = $percentOf($slotUsage['used'], $slotUsage['total']); @endphp
                <section class="{{ $card }} flex flex-col">
                    <h2 class="{{ $cardTitle }}">Slots filled this term</h2>
                    <div class="relative mx-auto mt-3 w-full max-w-[200px]">
                        <svg viewBox="0 0 120 66" class="w-full" role="img" aria-label="{{ $slotUsage['used'] }} of {{ $slotUsage['total'] }} slots filled">
                            <path d="M10 60 A50 50 0 0 1 110 60" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round" class="text-navy-50" />
                            @if ($slotPercent > 0)
                                <path d="M10 60 A50 50 0 0 1 110 60" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $slotPercent }} 100" class="text-navy-600" />
                            @endif
                        </svg>
                        <div class="absolute inset-x-0 bottom-0 text-center">
                            <p class="font-mono text-[18px] leading-tight text-ink sm:text-[20px]">{{ $slotPercent }}%</p>
                        </div>
                    </div>
                    <p class="mt-2 text-center font-body text-[12px] text-slate">
                        <span class="font-mono text-ink">{{ $slotUsage['used'] }}</span> of <span class="font-mono text-ink">{{ $slotUsage['total'] }}</span> slots awarded
                    </p>
                </section>

                {{-- Open offerings --}}
                <section class="{{ $card }} sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="{{ $cardTitle }}">Open offerings</h2>
                        <a href="{{ route('staff.applications.index') }}" class="rounded-control font-body text-[12px] font-500 text-navy-700 hover:underline focus-visible:outline-2 focus-visible:outline-navy-600">View all</a>
                    </div>
                    <ul class="mt-2.5 space-y-3">
                        @forelse ($openOfferings as $row)
                            @php
                                $offering = $row['offering'];
                                $used = $percentOf($row['used'], (int) $offering->total_slots);
                            @endphp
                            <li wire:key="open-offering-{{ $offering->id }}">
                                <a href="{{ $reviewUrl($offering) }}" class="block rounded-control focus-visible:outline-2 focus-visible:outline-navy-600">
                                    <p class="truncate font-body text-[12px] text-ink hover:text-navy-700 sm:text-[13px]">{{ $offering->scholarshipProgram->name }}</p>
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="min-w-0 truncate font-body text-[11px] text-mist">{{ $offering->displayName() }}</span>
                                        <span class="shrink-0 font-body text-[11px] {{ $row['daysLeft'] <= 7 ? 'text-amber-600' : 'text-mist' }}">
                                            {{ $row['daysLeft'] === 0 ? 'Closes today' : 'Closes '.$offering->closes_at->format('M j') }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="h-1.5 flex-1 rounded-full bg-navy-50">
                                            <div class="h-1.5 rounded-full {{ $used >= 100 ? 'bg-gold-600' : 'bg-navy-600' }}" style="width: {{ $used }}%"></div>
                                        </div>
                                        <span class="shrink-0 font-mono text-[11px] text-slate">{{ $row['used'] }}/{{ $offering->total_slots }}</span>
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li class="font-body text-[12px] text-mist sm:text-[13px]">
                                No offerings are open right now.
                                @if ($isAdmin)
                                    <a href="{{ route('admin.scholarship-programs') }}" class="font-500 text-navy-700 hover:underline">Open an offering</a>
                                @endif
                            </li>
                        @endforelse
                    </ul>
                </section>
            </div>

            @if ($isAdmin)
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                    {{-- Budget utilization --}}
                    <section class="{{ $card }}">
                        <h2 class="{{ $cardTitle }}">Budget utilization</h2>
                        <ul class="mt-2.5 space-y-3">
                            @forelse ($budgetUtilization as $row)
                                @php $spent = $percentOf((int) $row['awarded'], (int) $row['provider']->allocated_budget); @endphp
                                <li>
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="min-w-0 truncate font-body text-[12px] text-ink sm:text-[13px]">{{ $row['provider']->name }}</span>
                                        <span class="shrink-0 font-mono text-[11px] text-mist">{{ $spent }}%</span>
                                    </div>
                                    <div class="mt-1 h-1.5 rounded-full bg-navy-50">
                                        <div class="h-1.5 rounded-full {{ $spent >= 100 ? 'bg-crimson-600' : 'bg-navy-600' }}" style="width: {{ $spent }}%"></div>
                                    </div>
                                    <p class="mt-0.5 font-mono text-[11px] text-slate">₱{{ number_format($row['awarded'], 2) }} <span class="text-mist">/ ₱{{ number_format($row['provider']->allocated_budget, 2) }}</span></p>
                                </li>
                            @empty
                                <li class="font-body text-[12px] text-mist sm:text-[13px]">
                                    No provider has a budget set.
                                    <a href="{{ route('admin.providers') }}" class="font-500 text-navy-700 hover:underline">Set one under Providers</a>
                                </li>
                            @endforelse
                        </ul>
                    </section>

                    {{-- Slots across terms --}}
                    @php $trendTop = max(1, (int) $termTrends->max('totalSlots')); @endphp
                    <section class="{{ $card }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="{{ $cardTitle }}">Slots across terms</h2>
                            <div class="flex items-center gap-2.5">
                                <span class="flex items-center gap-1 font-body text-[11px] text-slate"><span class="size-2 rounded-sm bg-navy-100"></span>Offered</span>
                                <span class="flex items-center gap-1 font-body text-[11px] text-slate"><span class="size-2 rounded-sm bg-navy-600"></span>Awarded</span>
                            </div>
                        </div>
                        @if ($termTrends->isEmpty())
                            <p class="mt-2.5 font-body text-[12px] text-mist sm:text-[13px]">
                                No academic terms yet.
                                <a href="{{ route('admin.academic-terms') }}" class="font-500 text-navy-700 hover:underline">Add a term</a>
                            </p>
                        @else
                            <div class="mt-3 flex h-[120px] items-end gap-2 border-b border-line sm:gap-3">
                                @foreach ($termTrends as $row)
                                    <div
                                        class="group relative flex h-full flex-1 items-end justify-center gap-0.5"
                                        role="img"
                                        aria-label="{{ $row['term']->school_year }} {{ $row['term']->semester->label() }}: {{ $row['usedSlots'] }} of {{ $row['totalSlots'] }} slots awarded"
                                    >
                                        <div class="w-full max-w-[16px] rounded-t-sm bg-navy-100" style="height: {{ $row['totalSlots'] / $trendTop * 100 }}%"></div>
                                        <div class="w-full max-w-[16px] rounded-t-sm bg-navy-600" style="height: {{ $row['usedSlots'] / $trendTop * 100 }}%"></div>
                                        <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden w-max rounded-control border border-line bg-surface px-2.5 py-1.5 shadow-md group-hover:block">
                                            <p class="font-body text-[11px] text-mist">{{ $row['term']->school_year }} &middot; {{ $row['term']->semester->label() }}</p>
                                            <p class="font-mono text-[11px] text-ink">{{ $row['usedSlots'] }} / {{ $row['totalSlots'] }} slots</p>
                                            @if ($row['awardedAmount'] > 0)
                                                <p class="font-mono text-[11px] text-slate">₱{{ number_format($row['awardedAmount'], 2) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-1.5 flex gap-2 sm:gap-3" aria-hidden="true">
                                @foreach ($termTrends as $row)
                                    <span class="min-w-0 flex-1 text-center font-body text-[10px] leading-tight text-mist sm:text-[11px]">
                                        <span class="block truncate">{{ $row['term']->school_year }}</span>
                                        <span class="block truncate">{{ $row['term']->semester->label() }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    {{-- Records --}}
                    <section class="{{ $card }} sm:col-span-2 lg:col-span-1">
                        <h2 class="{{ $cardTitle }}">Records</h2>
                        <ul class="mt-2 grid grid-cols-1 gap-x-4 sm:grid-cols-2 lg:grid-cols-1">
                            @foreach ($records as $record)
                                <li>
                                    <a href="{{ route($record['route']) }}" class="flex items-center justify-between gap-2 rounded-control px-1 py-1.5 font-body text-[12px] text-slate hover:bg-paper hover:text-ink focus-visible:outline-2 focus-visible:outline-navy-600 sm:text-[13px]">
                                        {{ $record['label'] }}
                                        <span class="font-mono text-ink">{{ $record['value'] }}@if ($record['total'] !== null)<span class="text-mist"> / {{ $record['total'] }}</span>@endif</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </div>
            @endif
        </div>

        {{-- Side column --}}
        <div class="grid min-w-0 grid-cols-1 content-start gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-1">
            {{-- Pending tasks and shortcuts --}}
            @php
                $taskCount = ($tasks['pendingReview'] > 0 ? 1 : 0) + ($tasks['unverifiedDocuments'] > 0 ? 1 : 0) + $tasks['cappedOfferings']->count();
            @endphp
            <section class="{{ $card }}">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="{{ $cardTitle }}">Pending tasks</h2>
                    @if ($taskCount > 0)
                        <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-600 px-1.5 font-mono text-[11px] text-white">{{ $taskCount }}</span>
                    @endif
                </div>

                <ul class="mt-2 space-y-1">
                    <li>
                        <a href="{{ route('staff.applications.index') }}" class="flex items-center gap-2.5 rounded-control px-1.5 py-1.5 hover:bg-paper focus-visible:outline-2 focus-visible:outline-navy-600">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full {{ $tasks['pendingReview'] > 0 ? 'bg-amber-600/10 text-amber-600' : 'bg-paper text-mist' }}"><x-ui.icon name="clock" class="size-4" /></span>
                            <span class="min-w-0 flex-1 truncate font-body text-[12px] text-ink sm:text-[13px]">Applications to screen</span>
                            <span class="font-mono text-[13px] text-ink">{{ $tasks['pendingReview'] }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('staff.applications.index') }}" class="flex items-center gap-2.5 rounded-control px-1.5 py-1.5 hover:bg-paper focus-visible:outline-2 focus-visible:outline-navy-600">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full {{ $tasks['unverifiedDocuments'] > 0 ? 'bg-amber-600/10 text-amber-600' : 'bg-paper text-mist' }}"><x-ui.icon name="form" class="size-4" /></span>
                            <span class="min-w-0 flex-1 truncate font-body text-[12px] text-ink sm:text-[13px]">Documents to verify</span>
                            <span class="font-mono text-[13px] text-ink">{{ $tasks['unverifiedDocuments'] }}</span>
                        </a>
                    </li>
                    @foreach ($tasks['cappedOfferings'] as $offering)
                        <li wire:key="capped-{{ $offering->id }}">
                            <a href="{{ $reviewUrl($offering) }}" class="flex items-center gap-2.5 rounded-control px-1.5 py-1.5 hover:bg-paper focus-visible:outline-2 focus-visible:outline-navy-600">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-600/10 text-amber-600"><x-ui.icon name="alert-circle" class="size-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-body text-[12px] text-ink sm:text-[13px]">{{ $offering->scholarshipProgram->name }}</span>
                                    <span class="block font-body text-[11px] text-amber-600">Application cap reached</span>
                                </span>
                                <span class="font-mono text-[12px] text-ink">{{ $offering->applications_count }}/{{ $offering->max_applications }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3 grid grid-cols-4 gap-1.5 border-t border-line pt-3">
                    @foreach ([
                        ['label' => 'Applications', 'icon' => 'form', 'route' => 'staff.applications.index'],
                        ['label' => 'Grantees', 'icon' => 'award', 'route' => 'staff.grantees.index'],
                        ['label' => 'Reports', 'icon' => 'chart-bar', 'route' => 'staff.reports.index'],
                        ['label' => 'Notices', 'icon' => 'megaphone', 'route' => 'staff.announcements.index'],
                    ] as $shortcut)
                        <a href="{{ route($shortcut['route']) }}" class="group flex flex-col items-center gap-1 rounded-control py-1 focus-visible:outline-2 focus-visible:outline-navy-600">
                            <span class="flex size-9 items-center justify-center rounded-control border border-line text-navy-700 transition group-hover:border-navy-600 group-hover:bg-navy-50"><x-ui.icon :name="$shortcut['icon']" class="size-4" /></span>
                            <span class="font-body text-[10px] text-slate sm:text-[11px]">{{ $shortcut['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Recent awards --}}
            <section class="{{ $card }}">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="{{ $cardTitle }}">Recent awards</h2>
                    <a href="{{ route('staff.grantees.index') }}" class="rounded-control font-body text-[12px] font-500 text-navy-700 hover:underline focus-visible:outline-2 focus-visible:outline-navy-600">View all</a>
                </div>
                <ul class="mt-2 divide-y divide-line">
                    @forelse ($recentAwards as $award)
                        @php $name = trim($award->applicant->first_name.' '.$award->applicant->last_name); @endphp
                        <li wire:key="award-{{ $award->id }}">
                            <a href="{{ route('staff.grantees.show', $award) }}" class="flex items-center gap-2.5 rounded-control px-1 py-2 hover:bg-paper focus-visible:outline-2 focus-visible:outline-navy-600">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-navy-50 font-display text-[11px] font-600 text-navy-700">{{ $initials($name) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-body text-[12px] text-ink sm:text-[13px]">{{ $name }}</span>
                                    <span class="block truncate font-body text-[11px] text-mist">{{ $award->programOffering->scholarshipProgram->name }} &middot; {{ $award->programOffering->displayName() }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-mono text-[12px] text-ink">₱{{ number_format($award->amount, 0) }}</span>
                                    <span class="block font-body text-[10px] text-gold-600 sm:text-[11px]">{{ $award->application?->isRenewal() ? 'Renewed' : 'Awarded' }} {{ $award->awarded_at?->format('M j') }}</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="py-2 font-body text-[12px] text-mist sm:text-[13px]">
                            No awards recorded yet.
                            <a href="{{ route('staff.applications.index') }}" class="font-500 text-navy-700 hover:underline">Review applications</a>
                        </li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</div>
