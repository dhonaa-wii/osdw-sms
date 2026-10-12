<?php

namespace App\Services;

use App\Models\Award;
use App\Models\ProgramOffering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        protected RenewalService $renewalService,
    ) {}

    /**
     * The awards behind the Grantees page and the Reports page, filtered the
     * same way on both: one offering's awards when one is chosen, otherwise
     * each grantee's latest award per offering group.
     *
     * @return Builder<Award>
     */
    public function granteeQuery(string $search = '', ?int $programId = null, ?ProgramOffering $offering = null, ?int $collegeId = null): Builder
    {
        return Award::query()
            ->join('applicants', 'applicants.id', '=', 'awards.applicant_id')
            ->select('awards.*')
            ->when(
                $offering,
                fn ($query) => $query->where('awards.program_offering_id', $offering->id),
                fn ($query) => $query->whereIn('awards.id', $this->renewalService->latestAwardIdsPerChain()),
            )
            ->when($programId, fn ($query) => $query->whereHas('programOffering', fn ($query) => $query->where('scholarship_program_id', $programId)))
            ->when($collegeId, fn ($query) => $query->whereHas('application', fn ($query) => $query->where('college_id', $collegeId)))
            // Every word must match the student ID or a name, so "Juan Dela Cruz" finds Juan Dela Cruz.
            ->when(trim($search) !== '', function ($query) use ($search) {
                foreach (preg_split('/\s+/', trim($search)) as $word) {
                    $needle = '%'.$word.'%';

                    $query->where(fn ($query) => $query->where('applicants.student_id', 'like', $needle)
                        ->orWhere('applicants.last_name', 'like', $needle)
                        ->orWhere('applicants.first_name', 'like', $needle)
                        ->orWhere('applicants.middle_name', 'like', $needle));
                }
            })
            ->orderBy('applicants.last_name')
            ->orderBy('applicants.first_name');
    }

    /**
     * The system's headline output. Column order and source match the
     * schema doc's own mapping (§9) exactly. Built from that mapping since
     * no verified sample of the agency's actual file was available — treat
     * the column order/labels as unverified until checked against a real one.
     *
     * @param  Collection<int, Award>  $awards  from granteeQuery()
     * @return Collection<int, array<string, mixed>>
     */
    public function sucLevelling(Collection $awards): Collection
    {
        return $awards
            ->load(['application.applicant', 'application.college', 'programOffering.scholarshipProgram.provider', 'programOffering.campus'])
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
                    'Type' => $award->programOffering->category->label(),
                    'Funding' => $scholarshipProgram->provider->funding_source->label(),
                    'Campus' => $award->programOffering->campus->name,
                    'Amount' => $award->amount,
                ];
            });
    }
}
