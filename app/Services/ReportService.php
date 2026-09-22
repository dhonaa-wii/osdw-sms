<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Application;
use App\Models\ApplicationScreening;
use App\Models\Award;
use App\Models\ScholarTerm;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * The system's headline output. Column order and source match the
     * schema doc's own mapping (§9) exactly. Built from that mapping since
     * no verified sample of the agency's actual file was available — treat
     * the column order/labels as unverified until checked against a real one.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sucLevelling(AcademicTerm $term): Collection
    {
        return $this->mapSucLevellingRows(
            Award::where('status', 'active')->where('academic_term_id', $term->id)
        );
    }

    /**
     * Same headline report, scoped by an arbitrary awarded-date range instead
     * of a single term — for ad hoc agency requests that don't line up with
     * term boundaries. Either bound may be omitted for an open-ended range.
     */
    public function sucLevellingByDateRange(?string $from, ?string $to): Collection
    {
        return $this->mapSucLevellingRows(
            Award::where('status', 'active')
                ->when($from, fn ($query) => $query->whereDate('awarded_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('awarded_at', '<=', $to))
        );
    }

    private function mapSucLevellingRows($query): Collection
    {
        return $query
            ->with(['application.applicant', 'application.college', 'programOffering.scholarshipProgram.provider', 'programOffering.campus'])
            ->get()
            ->sortBy(fn (Award $award) => $award->application->college->name.$award->application->applicant->last_name)
            ->values()
            ->map(function (Award $award, int $index) {
                $applicant = $award->application->applicant;
                $scholarshipProgram = $award->programOffering->scholarshipProgram;

                return [
                    'Sequence No.' => $index + 1,
                    'Last Name' => $applicant->last_name,
                    'First Name' => $applicant->first_name,
                    'Middle Name' => $applicant->middle_name,
                    'Sex' => $applicant->sex->label(),
                    'Course' => $award->application->college->name,
                    'Year' => $award->application->year_level,
                    'College' => $award->application->college->name,
                    'Name of Scholarship' => $scholarshipProgram->name,
                    'Type' => $scholarshipProgram->category->label(),
                    'Funding' => $scholarshipProgram->provider->funding_source->label(),
                    'Campus' => $award->programOffering->campus->name,
                    'Amount' => $award->amount,
                ];
            });
    }

    /**
     * @return Collection<int, array{college: string, scholars: int, amount: float}>
     */
    public function scholarsPerCollege(AcademicTerm $term): Collection
    {
        return Award::where('status', 'active')
            ->where('academic_term_id', $term->id)
            ->with('application.college')
            ->get()
            ->groupBy(fn (Award $award) => $award->application->college->name)
            ->map(fn (Collection $awards, string $college) => [
                'college' => $college,
                'scholars' => $awards->count(),
                'amount' => $awards->sum('amount'),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{funding_source: string, scholars: int, amount: float}>
     */
    public function scholarsPerFundingSource(AcademicTerm $term): Collection
    {
        return Award::where('status', 'active')
            ->where('academic_term_id', $term->id)
            ->with('programOffering.scholarshipProgram.provider')
            ->get()
            ->groupBy(fn (Award $award) => $award->programOffering->scholarshipProgram->provider->funding_source->label())
            ->map(fn (Collection $awards, string $fundingSource) => [
                'funding_source' => $fundingSource,
                'scholars' => $awards->count(),
                'amount' => $awards->sum('amount'),
            ])
            ->values();
    }

    /**
     * @return array{received: int, eligible: int, shortlisted: int, awarded: int, accepted: int}
     */
    public function applicationFunnel(AcademicTerm $term): array
    {
        $applications = Application::whereHas(
            'programOffering',
            fn ($query) => $query->where('academic_term_id', $term->id)
        );

        return [
            'received' => (clone $applications)->count(),
            'eligible' => (clone $applications)->whereNotIn('status', ['draft'])->count(),
            'shortlisted' => (clone $applications)->whereIn('status', ['shortlisted', 'approved', 'accepted', 'waitlisted', 'denied'])->count(),
            'awarded' => (clone $applications)->whereHas('award')->count(),
            'accepted' => (clone $applications)->whereHas('award', fn ($q) => $q->where('acceptance_status', 'accepted'))->count(),
        ];
    }

    /**
     * @return array{continuing: int, terminated: int}
     */
    public function retentionReport(AcademicTerm $term): array
    {
        $terms = ScholarTerm::where('academic_term_id', $term->id)->get();

        return [
            'continuing' => $terms->whereIn('status', ['active', 'probation'])->count(),
            'terminated' => $terms->where('status', 'terminated')->count(),
        ];
    }

    /**
     * @return Collection<int, array{applicant: string, student_id: string, awards: int}>
     */
    public function multipleAwardReport(AcademicTerm $term): Collection
    {
        return Award::where('status', 'active')
            ->where('academic_term_id', $term->id)
            ->with('applicant')
            ->get()
            ->groupBy('applicant_id')
            ->filter(fn (Collection $awards) => $awards->count() > 1)
            ->map(fn (Collection $awards) => [
                'applicant' => $awards->first()->applicant->last_name.', '.$awards->first()->applicant->first_name,
                'student_id' => $awards->first()->applicant->student_id,
                'awards' => $awards->count(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{reason: string, count: int}>
     */
    public function disqualificationReport(AcademicTerm $term): Collection
    {
        return ApplicationScreening::where('passed', false)
            ->whereHas('application.programOffering', fn ($query) => $query->where('academic_term_id', $term->id))
            ->get()
            ->groupBy('message')
            ->map(fn (Collection $rows, string $message) => ['reason' => $message, 'count' => $rows->count()])
            ->sortByDesc('count')
            ->values();
    }
}
