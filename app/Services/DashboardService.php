<?php

namespace App\Services;

use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Models\AcademicTerm;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Award;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The figures on the staff and admin dashboard. Everything is counted live —
 * nothing here is stored — and "this term" always means the academic term
 * marked current.
 */
class DashboardService
{
    public function __construct(
        protected AwardService $awardService,
        protected RenewalService $renewalService,
    ) {}

    public function currentTerm(): ?AcademicTerm
    {
        return AcademicTerm::where('is_current', true)->first();
    }

    /**
     * Applications to offerings in the given term. With no current term the
     * query matches nothing, so every card reads zero instead of mixing terms.
     */
    public function termApplications(?AcademicTerm $term): Builder
    {
        return $term
            ? Application::whereHas('programOffering', fn ($q) => $q->where('academic_term_id', $term->id))
            : Application::query()->whereRaw('1 = 0');
    }

    /**
     * The funnel for the term: received, screened, shortlisted, awarded.
     *
     * @return array<string, int>
     */
    public function funnel(?AcademicTerm $term): array
    {
        $applications = $this->termApplications($term);

        return [
            'received' => (clone $applications)->count(),
            'screened' => (clone $applications)->whereNotNull('screened_at')->count(),
            'shortlisted' => (clone $applications)->where('status', ApplicationStatus::Shortlisted->value)->count(),
            'awarded' => (clone $applications)->whereHas('award')->count(),
        ];
    }

    /**
     * Where the term's applications stand right now, for the status bar.
     *
     * @return array<int, array{label: string, value: int, bar: string}>
     */
    public function statusBreakdown(?AcademicTerm $term): array
    {
        $applications = $this->termApplications($term);
        $pending = [ApplicationStatus::Submitted->value, ApplicationStatus::Screening->value, ApplicationStatus::Waitlisted->value];

        return [
            ['label' => 'Pending', 'value' => (clone $applications)->whereIn('status', $pending)->count(), 'bar' => 'bg-amber-600'],
            ['label' => 'Shortlisted', 'value' => (clone $applications)->where('status', ApplicationStatus::Shortlisted->value)->count(), 'bar' => 'bg-navy-600'],
            ['label' => 'Awarded', 'value' => (clone $applications)->whereHas('award')->count(), 'bar' => 'bg-gold-600'],
            ['label' => 'Denied', 'value' => (clone $applications)->where('status', ApplicationStatus::Denied->value)->count(), 'bar' => 'bg-crimson-600'],
        ];
    }

    /**
     * Applications received and screened per day over the last $days days,
     * oldest first. Days with nothing still appear, as zero.
     *
     * @return Collection<int, array{date: Carbon, received: int, screened: int}>
     */
    public function dailyActivity(int $days): Collection
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $countPerDay = fn (string $column) => Application::where($column, '>=', $since)
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $received = $countPerDay('created_at');
        $screened = $countPerDay('screened_at');

        return collect(range(0, $days - 1))->map(function (int $i) use ($since, $received, $screened) {
            $date = $since->copy()->addDays($i);
            $key = $date->format('Y-m-d');

            return [
                'date' => $date,
                'received' => (int) ($received[$key] ?? 0),
                'screened' => (int) ($screened[$key] ?? 0),
            ];
        });
    }

    /**
     * Slots awarded to new applicants against the slots offered this term.
     * Renewals take no slot, so they are left out.
     *
     * @return array{used: int, total: int}
     */
    public function slotUtilization(?AcademicTerm $term): array
    {
        if (! $term) {
            return ['used' => 0, 'total' => 0];
        }

        $offerings = ProgramOffering::where('academic_term_id', $term->id)->get();

        return [
            'used' => $offerings->sum(fn (ProgramOffering $offering) => $this->awardService->usedSlots($offering)),
            'total' => (int) $offerings->sum('total_slots'),
        ];
    }

    /**
     * Offerings taking applications now, soonest deadline first.
     *
     * @return Collection<int, array{offering: ProgramOffering, used: int, daysLeft: int}>
     */
    public function openOfferings(int $limit = 5): Collection
    {
        return ProgramOffering::open()
            ->with('scholarshipProgram', 'academicTerm')
            ->orderBy('closes_at')
            ->take($limit)
            ->get()
            ->map(fn (ProgramOffering $offering) => [
                'offering' => $offering,
                'used' => $this->awardService->usedSlots($offering),
                'daysLeft' => (int) now()->startOfDay()->diffInDays($offering->closes_at->copy()->startOfDay()),
            ]);
    }

    /**
     * Pending work across every term, not only the current one.
     *
     * @return array{pendingReview: int, unverifiedDocuments: int, cappedOfferings: Collection<int, ProgramOffering>}
     */
    public function pendingTasks(): array
    {
        return [
            'pendingReview' => Application::where('status', ApplicationStatus::Screening->value)->count(),
            'unverifiedDocuments' => ApplicationDocument::where('status', DocumentStatus::Pending->value)->count(),
            'cappedOfferings' => ProgramOffering::whereNotNull('max_applications')
                ->where('closes_at', '>', now())
                ->with('scholarshipProgram')
                ->withCount(['applications' => fn ($q) => $q->where('kind', ApplicationKind::New->value)])
                ->get()
                ->filter(fn (ProgramOffering $offering) => $offering->applications_count >= $offering->max_applications)
                ->values(),
        ];
    }

    /**
     * @return Collection<int, Award>
     */
    public function recentAwards(int $limit = 6): Collection
    {
        return Award::with('applicant', 'application', 'programOffering.scholarshipProgram')
            ->latest('awarded_at')
            ->take($limit)
            ->get();
    }

    /**
     * Total and used slots, and amount awarded, for the latest terms, oldest first.
     *
     * @return Collection<int, array{term: AcademicTerm, totalSlots: int, usedSlots: int, awardedAmount: float}>
     */
    public function termTrends(int $terms = 5): Collection
    {
        return AcademicTerm::orderByDesc('start_date')
            ->take($terms)
            ->get()
            ->reverse()
            ->values()
            ->map(function (AcademicTerm $term) {
                $offeringIds = ProgramOffering::where('academic_term_id', $term->id)->pluck('id');

                return [
                    'term' => $term,
                    'totalSlots' => (int) ProgramOffering::whereIn('id', $offeringIds)->sum('total_slots'),
                    'usedSlots' => Award::whereIn('program_offering_id', $offeringIds)
                        ->whereHas('application', fn ($q) => $q->where('kind', ApplicationKind::New->value))
                        ->count(),
                    'awardedAmount' => (float) Award::whereIn('program_offering_id', $offeringIds)->sum('amount'),
                ];
            });
    }

    /**
     * Scholarship programs and providers matching the search. A program also
     * matches on its provider's name. Each program carries the offering its
     * review pages should open on — the latest in its newest group.
     *
     * @return array{programs: Collection<int, array{program: ScholarshipProgram, latest: ?ProgramOffering}>, providers: Collection<int, Provider>}
     */
    public function search(string $term, int $limit = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return ['programs' => collect(), 'providers' => collect()];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $programs = ScholarshipProgram::with('provider')
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhereHas('provider', fn ($q) => $q->where('name', 'like', $like)))
            ->orderBy('name')
            ->take($limit)
            ->get()
            ->map(function (ScholarshipProgram $program) {
                $first = ProgramOffering::where('scholarship_program_id', $program->id)
                    ->whereNull('renews_offering_id')
                    ->where('opens_at', '<=', now())
                    ->latest('opens_at')
                    ->first();

                return [
                    'program' => $program,
                    'latest' => $first ? $this->renewalService->chainOf($first)->last() : null,
                ];
            });

        $providers = Provider::withCount('scholarshipPrograms')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->take($limit)
            ->get();

        return ['programs' => $programs, 'providers' => $providers];
    }
}
