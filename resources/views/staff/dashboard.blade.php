<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Award;
use App\Models\College;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AwardService;
use App\Services\ProviderService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Dashboard'))] class extends Component
{
    protected function cappedOfferings()
    {
        return ProgramOffering::whereNotNull('max_applications')
            ->where('closes_at', '>', now())
            ->withCount('applications')
            ->get()
            ->filter(fn (ProgramOffering $offering) => $offering->applications_count >= $offering->max_applications)
            ->values();
    }

    public function with(): array
    {
        if (auth()->user()->role === UserRole::Admin) {
            $activeAwards = fn ($query) => $query
                ->whereIn('status', ['reserved', 'active'])
                ->whereNotIn('acceptance_status', ['declined', 'forfeited']);

            return [
                'cappedOfferings' => $this->cappedOfferings(),
                'currentTerm' => AcademicTerm::where('is_current', true)->first(),
                'collegeCount' => College::count(),
                'activeCollegeCount' => College::where('is_active', true)->count(),
                'programCount' => Program::count(),
                'providerCount' => Provider::count(),
                'activeProviderCount' => Provider::where('is_active', true)->count(),
                'scholarshipProgramCount' => ScholarshipProgram::count(),
                'activeScholarshipProgramCount' => ScholarshipProgram::where('is_active', true)->count(),
                'academicTermCount' => AcademicTerm::count(),
                'adminCount' => User::where('role', UserRole::Admin)->count(),
                'staffCount' => User::where('role', UserRole::OsdwStaff)->count(),
                'budgetUtilization' => app(ProviderService::class)->budgetUtilization(),
                'termTrends' => AcademicTerm::orderByDesc('start_date')
                    ->take(5)
                    ->get()
                    ->map(function ($term) use ($activeAwards) {
                        $offeringIds = ProgramOffering::where('academic_term_id', $term->id)->pluck('id');

                        return [
                            'term' => $term,
                            'totalSlots' => ProgramOffering::where('academic_term_id', $term->id)->sum('total_slots'),
                            'usedSlots' => $activeAwards(Award::whereIn('program_offering_id', $offeringIds))->count(),
                            'awardedAmount' => $activeAwards(Award::whereIn('program_offering_id', $offeringIds))->sum('amount'),
                        ];
                    }),
            ];
        }

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termApplications = $currentTerm
            ? Application::whereHas('programOffering', fn ($q) => $q->where('academic_term_id', $currentTerm->id))
            : Application::query()->whereRaw('1 = 0');

        $openOfferinges = ProgramOffering::open()
            ->with('scholarshipProgram', 'academicTerm')
            ->orderBy('closes_at')
            ->get();

        $service = app(AwardService::class);

        $data = [
            'cappedOfferings' => $this->cappedOfferings(),
            'currentTerm' => $currentTerm,
            'received' => (clone $termApplications)->count(),
            'screened' => (clone $termApplications)->whereNotNull('screened_at')->count(),
            'shortlisted' => (clone $termApplications)->where('status', 'shortlisted')->count(),
            'awarded' => (clone $termApplications)->whereHas('award')->count(),
            'openOfferinges' => $openOfferinges,
            'closingSoon' => $openOfferinges->filter(fn ($o) => $o->closes_at->lte(now()->addDays(7))),
            'pendingReview' => Application::where('status', 'screening')->count(),
            'unverifiedDocuments' => ApplicationDocument::where('status', 'pending')->count(),
            'slotUtilization' => $openOfferinges->take(5)->map(fn ($offering) => [
                'offering' => $offering,
                'used' => $service->usedSlots($offering),
            ]),
        ];

        return $data;
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <h1 class="font-display text-[30px] font-600 text-ink">Dashboard</h1>
        <x-ui.attention-badge :offerings="$cappedOfferings" />
    </div>

    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
        @unless ($currentTerm)
            <x-ui.alert type="warning" class="mt-6">No academic term is marked current &mdash; set one in Application Settings so staff dashboards report accurate figures.</x-ui.alert>
        @endunless

        <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <a href="{{ route('admin.colleges') }}" class="group flex items-center gap-3 rounded-card border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V8l8-5 8 5v13" /><path d="M10 21v-6h4v6" /></svg>
                </span>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Colleges</p>
                    <p class="font-mono text-[20px] leading-tight text-ink">{{ $activeCollegeCount }} <span class="text-[13px] text-mist">/ {{ $collegeCount }}</span></p>
                </div>
            </a>
            <a href="{{ route('admin.programs') }}" class="group flex items-center gap-3 rounded-card border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 9-10-5L2 9l10 5 10-5Z" /><path d="M6 11v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4" /></svg>
                </span>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Programs</p>
                    <p class="font-mono text-[20px] leading-tight text-ink">{{ $programCount }}</p>
                </div>
            </a>
            <a href="{{ route('admin.providers') }}" class="group flex items-center gap-3 rounded-card border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M3 12h18" /></svg>
                </span>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Providers</p>
                    <p class="font-mono text-[20px] leading-tight text-ink">{{ $activeProviderCount }} <span class="text-[13px] text-mist">/ {{ $providerCount }}</span></p>
                </div>
            </a>
            <a href="{{ route('admin.scholarship-programs') }}" class="group flex items-center gap-3 rounded-card border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" /></svg>
                </span>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Scholarship programs</p>
                    <p class="font-mono text-[20px] leading-tight text-ink">{{ $activeScholarshipProgramCount }} <span class="text-[13px] text-mist">/ {{ $scholarshipProgramCount }}</span></p>
                </div>
            </a>
            <a href="{{ route('admin.academic-terms') }}" class="group flex items-center gap-3 rounded-card border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
                </span>
                <div>
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Academic terms</p>
                    <p class="font-mono text-[20px] leading-tight text-ink">{{ $academicTermCount }}</p>
                </div>
            </a>
        </div>

        <div class="mt-6 rounded-card border border-line bg-surface p-5">
            <div class="flex items-center gap-2">
                <svg class="size-5 text-forest-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" /><path d="M16 8.5a3 3 0 1 0 0-6" /><path d="M17.5 14.3c2.3.6 4 2.7 4 5.7" /></svg>
                <p class="font-display text-[16px] font-600 text-ink">User accounts</p>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <a href="{{ route('admin.users') }}" class="flex items-center justify-between rounded-control border border-line bg-paper px-3 py-2 transition hover:border-forest-600">
                    <span class="font-body text-[13px] text-ink">System Administrator</span>
                    <span class="font-mono text-[14px] text-ink">{{ $adminCount }}</span>
                </a>
                <a href="{{ route('admin.users') }}" class="flex items-center justify-between rounded-control border border-line bg-paper px-3 py-2 transition hover:border-forest-600">
                    <span class="font-body text-[13px] text-ink">OSDW Staff</span>
                    <span class="font-mono text-[14px] text-ink">{{ $staffCount }}</span>
                </a>
            </div>
        </div>

        <div class="mt-10">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Executive summary</p>

            <div class="mt-3 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-card border border-line bg-surface p-5">
                    <p class="font-display text-[16px] font-600 text-ink">Budget utilization</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($budgetUtilization as $row)
                            @php $percent = $row['provider']->allocated_budget > 0 ? min(100, round($row['awarded'] / $row['provider']->allocated_budget * 100)) : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between font-body text-[14px]">
                                    <span class="text-ink">{{ $row['provider']->name }}</span>
                                    <span class="font-mono text-slate">&#8369;{{ number_format($row['awarded'], 2) }} / &#8369;{{ number_format($row['provider']->allocated_budget, 2) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-paper">
                                    <div class="h-1.5 rounded-full {{ $percent >= 100 ? 'bg-crimson-600' : 'bg-forest-600' }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="font-body text-[14px] text-mist">No provider has an allocated budget set yet &mdash; add one under Providers.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-card border border-line bg-surface p-5">
                    <p class="font-display text-[16px] font-600 text-ink">Trends across terms</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($termTrends as $row)
                            @php $percent = $row['totalSlots'] > 0 ? min(100, round($row['usedSlots'] / $row['totalSlots'] * 100)) : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between font-body text-[14px]">
                                    <span class="text-ink">{{ $row['term']->school_year }} &middot; {{ $row['term']->semester->label() }}</span>
                                    <span class="font-mono text-slate">
                                        {{ $row['usedSlots'] }} / {{ $row['totalSlots'] }} slots
                                        @if ($row['awardedAmount'] > 0)
                                            &middot; &#8369;{{ number_format($row['awardedAmount'], 2) }}
                                        @endif
                                    </span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-paper">
                                    <div class="h-1.5 rounded-full bg-azure-600" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="font-body text-[14px] text-mist">No academic terms yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @else
        @unless ($currentTerm)
            <x-ui.alert type="warning" class="mt-6">No academic term is marked current &mdash; set one in Application Settings for accurate figures here.</x-ui.alert>
        @endunless

        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v10" /><path d="m8 9 4 4 4-4" /><path d="M4 17h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" /></svg>
                    </span>
                    <div>
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Received</p>
                        <p class="font-mono text-[22px] leading-tight text-ink">{{ $received }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-azure-600/10 text-azure-600">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="6" /><path d="m20 20-4.3-4.3" /><path d="m7.5 10 1.8 1.8L13 8" /></svg>
                    </span>
                    <div>
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Screened</p>
                        <p class="font-mono text-[22px] leading-tight text-ink">{{ $screened }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6-4.5-4.2 6.1-.7Z" /></svg>
                    </span>
                    <div>
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Shortlisted</p>
                        <p class="font-mono text-[22px] leading-tight text-ink">{{ $shortlisted }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" /></svg>
                    </span>
                    <div>
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Awarded</p>
                        <p class="font-mono text-[22px] leading-tight text-ink">{{ $awarded }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-display text-[16px] font-600 text-ink">Pending tasks</p>
                <div class="mt-3 space-y-3 font-body text-[14px]">
                    <div class="flex items-center gap-2.5">
                        <svg class="size-4 shrink-0 text-mist" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
                        <span class="flex-1 text-ink">Applications awaiting review</span>
                        <span class="font-mono {{ $pendingReview > 0 ? 'text-amber-600' : 'text-slate' }}">{{ $pendingReview }}</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <svg class="size-4 shrink-0 text-mist" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M9 8h6M9 12h6" /></svg>
                        <span class="flex-1 text-ink">Unverified documents</span>
                        <span class="font-mono {{ $unverifiedDocuments > 0 ? 'text-amber-600' : 'text-slate' }}">{{ $unverifiedDocuments }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5">
                <p class="font-display text-[16px] font-600 text-ink">Programs closing soon</p>
                <div class="mt-3 space-y-2">
                    @forelse ($closingSoon as $offering)
                        <a href="{{ route('staff.applications.workspace', $offering) }}" class="flex items-center gap-2.5 font-body text-[14px] transition-colors hover:text-forest-700">
                            <svg class="size-4 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
                            <span class="flex-1 text-ink">{{ $offering->scholarshipProgram->name }} <span class="text-mist">&middot; {{ $offering->displayName() }}</span></span>
                            <span class="font-mono text-amber-600">{{ $offering->closes_at->format('M j') }}</span>
                        </a>
                    @empty
                        <p class="font-body text-[14px] text-mist">Nothing closing in the next 7 days.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-card border border-line bg-surface p-5 lg:col-span-2">
                <p class="font-display text-[16px] font-600 text-ink">Slot utilization</p>
                <div class="mt-3 space-y-3">
                    @forelse ($slotUtilization as $row)
                        @php $percent = $row['offering']->total_slots > 0 ? min(100, round($row['used'] / $row['offering']->total_slots * 100)) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between font-body text-[14px]">
                                <a href="{{ route('staff.awards.workspace', $row['offering']) }}" class="text-ink hover:text-forest-700">{{ $row['offering']->scholarshipProgram->name }} <span class="text-mist">&middot; {{ $row['offering']->displayName() }}</span></a>
                                <span class="font-mono text-slate">{{ $row['used'] }} / {{ $row['offering']->total_slots }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-paper">
                                <div class="h-1.5 rounded-full {{ $percent >= 100 ? 'bg-crimson-600' : 'bg-forest-600' }}" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="font-body text-[14px] text-mist">No open offerings right now.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
