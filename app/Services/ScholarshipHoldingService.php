<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Award;
use App\Models\ProgramOffering;
use Illuminate\Support\Collection;

/**
 * Which scholarships a student currently holds, and the one-national-scholarship
 * rule built on it: a grantee of a national scholarship (government or
 * non-government) can't apply to or be awarded another national one. Local
 * and other scholarships are never limited.
 */
class ScholarshipHoldingService
{
    /** @var Collection<int, int>|null */
    protected ?Collection $roots = null;

    public function __construct(protected RenewalService $renewals) {}

    /**
     * The scholarships a student holds now: their latest award in each
     * program chain, unless a later renewal in that chain was denied (the
     * scholarship ended). Pass an offering to leave its own chain out.
     *
     * @return Collection<int, Award>
     */
    public function heldAwards(Applicant|int $applicant, ?ProgramOffering $exceptChainOf = null): Collection
    {
        $applicantId = $applicant instanceof Applicant ? $applicant->id : $applicant;

        return $this->heldAwardsAmong(collect([$applicantId]), $exceptChainOf)->get($applicantId, collect());
    }

    /**
     * heldAwards() for many students in two queries, keyed by applicant id —
     * for list pages.
     *
     * @param  Collection<int, int>  $applicantIds
     * @return Collection<int, Collection<int, Award>>
     */
    public function heldAwardsAmong(Collection $applicantIds, ?ProgramOffering $exceptChainOf = null): Collection
    {
        $roots = $this->roots();
        $exceptRoot = $exceptChainOf ? $roots->get($exceptChainOf->id) : null;
        $chainKey = fn ($model) => $model->applicant_id.'-'.$roots->get($model->program_offering_id);
        $byTerm = fn ($model) => [$model->programOffering->academicTerm->start_date, $model->id];

        // The latest application in each chain tells whether the scholarship is still running.
        $latestApplications = Application::whereIn('applicant_id', $applicantIds)
            ->with('programOffering.academicTerm')
            ->get()
            ->sortBy($byTerm)
            ->keyBy($chainKey);

        return Award::whereIn('applicant_id', $applicantIds)
            ->with(['programOffering.scholarshipProgram.provider', 'programOffering.academicTerm'])
            ->get()
            ->sortBy($byTerm)
            ->keyBy($chainKey)
            ->reject(fn (Award $award) => $exceptRoot !== null && $roots->get($award->program_offering_id) === $exceptRoot)
            ->reject(function (Award $award, string $key) use ($latestApplications) {
                $latest = $latestApplications->get($key);

                return $latest && $latest->isRenewal() && $latest->status === ApplicationStatus::Denied;
            })
            ->groupBy('applicant_id');
    }

    public function isNational(ProgramOffering $offering): bool
    {
        return (bool) $offering->scholarshipProgram->provider?->funding_source?->isNational();
    }

    /**
     * The national scholarship a student holds outside the given offering's
     * chain, if any.
     */
    public function nationalAward(Applicant|int $applicant, ?ProgramOffering $exceptChainOf = null): ?Award
    {
        return $this->heldAwards($applicant, $exceptChainOf)
            ->first(fn (Award $award) => $this->isNational($award->programOffering));
    }

    /**
     * Why a student can't apply to an offering under the one-national rule,
     * or null when they can.
     */
    public function applyBlocker(Applicant $applicant, ProgramOffering $offering): ?string
    {
        if (! $this->isNational($offering) || ! ($held = $this->nationalAward($applicant, $offering))) {
            return null;
        }

        return "You're already a grantee of {$held->programOffering->scholarshipProgram->name}, a national scholarship, so you can't apply to another national scholarship. You can still apply to local scholarships.";
    }

    /**
     * Why a new application can't be endorsed or awarded under the
     * one-national rule — the student was awarded another national
     * scholarship — or null when it can.
     */
    public function awardBlocker(Application $application): ?string
    {
        $held = $this->blockingAward($application);

        return $held
            ? "Already a grantee of {$held->programOffering->scholarshipProgram->name} · {$held->programOffering->displayName()}, a national scholarship. A student can hold only one national scholarship."
            : null;
    }

    /**
     * The national scholarship that rules a new application to a national
     * offering out, if the student holds one.
     */
    public function blockingAward(Application $application): ?Award
    {
        if ($application->isRenewal() || $application->award || ! $this->isNational($application->programOffering)) {
            return null;
        }

        return $this->nationalAward($application->applicant_id, $application->programOffering);
    }

    /** @return Collection<int, int> */
    protected function roots(): Collection
    {
        return $this->roots ??= $this->renewals->rootIdsByOffering();
    }
}
