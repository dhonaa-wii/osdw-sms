<?php

namespace App\Services;

use App\Enums\AllocationMode;
use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\CollegeScope;
use App\Enums\EmailType;
use App\Enums\Semester;
use App\Mail\RenewalNoticeMail;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Award;
use App\Models\ProgramOffering;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Renewal pipeline: admin opens a renewal offering that continues an earlier
 * offering of the same program. Every scholar awarded in that offering gets
 * a renewal application in it, skipping the form, screening, shortlist and
 * interview. The scholar hands in the offering's renewal requirements
 * (uploads and/or physical copies), staff verify them on the Renewals page,
 * then renew (an award) or deny. A renewal offering can itself be continued
 * by a later one, so renewals chain term after term until staff deny one,
 * e.g. because the scholar graduated.
 */
class RenewalService
{
    public function __construct(
        protected EmailService $emailService,
    ) {}

    /**
     * Offerings of the same program a renewal offering can continue: any
     * other offering, except ones that already continue this one (directly
     * or further down the chain), which would make a loop.
     *
     * @return Collection<int, ProgramOffering>
     */
    public function continuableOfferings(ProgramOffering $offering): Collection
    {
        $excluded = $offering->exists ? $this->descendantIds($offering)->push($offering->id) : collect();

        return ProgramOffering::where('scholarship_program_id', $offering->scholarship_program_id)
            ->whereNotIn('id', $excluded)
            ->with('academicTerm')
            ->get()
            ->sortByDesc(fn (ProgramOffering $candidate) => $candidate->academicTerm->start_date)
            ->values();
    }

    /**
     * The first offering of a renewal chain — the one scholars originally
     * applied to.
     */
    public function rootOf(ProgramOffering $offering): ProgramOffering
    {
        $root = $offering;
        $seen = [$root->id];

        while ($root->renews_offering_id !== null && ! in_array($root->renews_offering_id, $seen, true)) {
            $root = $root->renewsOffering;
            $seen[] = $root->id;
        }

        return $root;
    }

    /**
     * Every offering in the chain an offering belongs to: the first offering
     * followed by each renewal offering that continues it, oldest term first.
     *
     * @return Collection<int, ProgramOffering>
     */
    public function chainOf(ProgramOffering $offering): Collection
    {
        $root = $this->rootOf($offering);

        return ProgramOffering::whereIn('id', $this->descendantIds($root)->push($root->id))
            ->with('academicTerm')
            ->get()
            ->sortBy(fn (ProgramOffering $member) => [$member->academicTerm->start_date, $member->id])
            ->values();
    }

    /**
     * The first offering of every offering's chain, keyed by offering id —
     * worked out in one query rather than walking each chain separately.
     *
     * @return Collection<int, int>
     */
    public function rootIdsByOffering(): Collection
    {
        $parents = ProgramOffering::pluck('renews_offering_id', 'id');

        return $parents->map(function ($parentId, $id) use ($parents) {
            $root = $id;
            $seen = [$id];

            while (($next = $parents->get($root)) !== null && ! in_array($next, $seen, true)) {
                $root = $next;
                $seen[] = $root;
            }

            return $root;
        });
    }

    /**
     * One award per scholar per chain: the one from the latest offering, so
     * a scholar renewed term after term is listed once, at their newest award.
     *
     * @return Collection<int, int>
     */
    public function latestAwardIdsPerChain(): Collection
    {
        $roots = $this->rootIdsByOffering();

        return Award::query()
            ->join('academic_terms', 'academic_terms.id', '=', 'awards.academic_term_id')
            ->orderBy('academic_terms.start_date')
            ->orderBy('awards.id')
            ->get(['awards.id', 'awards.applicant_id', 'awards.program_offering_id'])
            ->keyBy(fn (Award $award) => $award->applicant_id.'-'.$roots->get($award->program_offering_id))
            ->pluck('id')
            ->values();
    }

    /**
     * The scholar's applications in an award's chain, oldest term first, up
     * to and including the one that award was made from — the original
     * application followed by each renewal.
     *
     * @return Collection<int, Application>
     */
    public function historyOf(Award $award): Collection
    {
        $chain = $this->chainOf($award->programOffering);
        $upTo = $chain->search(fn (ProgramOffering $member) => $member->id === $award->program_offering_id);
        $offeringIds = $chain->take($upTo + 1)->pluck('id');

        return Application::where('applicant_id', $award->applicant_id)
            ->whereIn('program_offering_id', $offeringIds)
            ->with([
                'programOffering.scholarshipProgram', 'programOffering.academicTerm', 'college', 'award',
                'documents.offeringFormField', 'fieldResponses.offeringFormField',
            ])
            ->get()
            ->sortBy(fn (Application $application) => $offeringIds->search($application->program_offering_id))
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    protected function descendantIds(ProgramOffering $offering): Collection
    {
        $ids = collect();
        $frontier = collect([$offering->id]);

        while ($frontier->isNotEmpty()) {
            $frontier = ProgramOffering::whereIn('renews_offering_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id');
            $ids = $ids->merge($frontier);
        }

        return $ids;
    }

    /**
     * A scholar in 4th year or above during a second-semester term is in
     * their last term. They can still renew; the count only suggests how
     * many new applicants the next renewal offering could take.
     */
    public const FINAL_YEAR_LEVEL = 4;

    public function isLastTerm(Application $application): bool
    {
        return $application->year_level !== null
            && $application->year_level >= self::FINAL_YEAR_LEVEL
            && $application->programOffering->academicTerm->semester === Semester::Second;
    }

    /**
     * Scholars awarded in an offering who were in their last term there —
     * the suggested number of new-applicant slots for the renewal offering
     * that continues it.
     */
    public function graduatingCount(ProgramOffering $source): int
    {
        return Award::where('program_offering_id', $source->id)
            ->with('application.programOffering.academicTerm')
            ->get()
            ->filter(fn (Award $award) => $this->isLastTerm($award->application))
            ->count();
    }

    /**
     * Attributes a renewal offering carries when it takes no new applicants:
     * no slots, cap, college restriction or quota.
     *
     * @return array<string, mixed>
     */
    public function renewalOfferingDefaults(): array
    {
        return [
            'total_slots' => 0,
            'max_applications' => null,
            'college_scope' => CollegeScope::All->value,
            'allocation_mode' => AllocationMode::Open->value,
        ];
    }

    /**
     * Scholars awarded in the offering this one continues who don't have a
     * renewal here yet.
     *
     * @return Collection<int, Award>
     */
    public function dueAwards(ProgramOffering $target): Collection
    {
        if (! $target->isRenewal()) {
            return collect();
        }

        $alreadyInTarget = $target->applications()->pluck('applicant_id');

        return Award::where('program_offering_id', $target->renews_offering_id)
            ->whereNotIn('applicant_id', $alreadyInTarget)
            ->with('application', 'applicant', 'programOffering.academicTerm')
            ->get()
            ->unique('applicant_id')
            ->values();
    }

    /**
     * The "Open renewals" command. Creates a renewal application in the
     * target offering for every due scholar and emails each one. Safe to run
     * again — scholars who already have one are skipped.
     *
     * @return int how many renewals were opened
     */
    public function openRenewals(ProgramOffering $target, User $staff): int
    {
        if (! $target->isRenewal()) {
            throw new \RuntimeException('Only a renewal offering takes renewing scholars.');
        }

        $renewals = DB::transaction(fn () => $this->dueAwards($target)->map(function (Award $award) use ($target, $staff) {
            $renewal = Application::create([
                'applicant_id' => $award->applicant_id,
                'program_offering_id' => $target->id,
                'kind' => ApplicationKind::Renewal,
                'renewed_from_award_id' => $award->id,
                'college_id' => $award->application->college_id,
                // The scholar enters this term's year level with their renewal requirements.
                'year_level' => null,
                'consent_publish' => $award->application->consent_publish,
                'status' => ApplicationStatus::Screening,
                'submitted_at' => now(),
            ]);

            ApplicationStatusLog::create([
                'application_id' => $renewal->id,
                'from_status' => null,
                'to_status' => ApplicationStatus::Screening,
                'changed_by' => $staff->id,
                'reason' => 'Renewal opened from '.$award->programOffering->displayName(),
            ]);

            return $renewal;
        }));

        foreach ($renewals as $renewal) {
            $this->emailService->send(
                $renewal,
                EmailType::RenewalNotice,
                new RenewalNoticeMail($renewal),
                $renewal->applicant->email,
            );
        }

        return $renewals->count();
    }

    /**
     * The scholar's year level this term, entered from their tracking page
     * alongside the renewal requirements. Submitted once — it cannot be
     * changed afterwards.
     */
    public function setYearLevel(Application $renewal, int $yearLevel): void
    {
        if (! $renewal->isRenewal() || $renewal->status !== ApplicationStatus::Screening) {
            throw new \RuntimeException('This renewal is already decided.');
        }

        if ($renewal->year_level !== null) {
            throw new \RuntimeException('Your year level is already submitted.');
        }

        if (! $renewal->programOffering->acceptsRenewalSubmissions()) {
            throw new \RuntimeException('The renewal submission window is closed.');
        }

        $renewal->update(['year_level' => $yearLevel]);
    }

    /**
     * End the scholarship here — the requirements show the scholar no longer
     * qualifies, graduated, or is no longer enrolled.
     */
    public function deny(Application $renewal, string $reason, User $staff): void
    {
        if (! $renewal->isRenewal() || $renewal->status !== ApplicationStatus::Screening) {
            throw new \RuntimeException('This renewal is already decided.');
        }

        $from = $renewal->status;
        $renewal->update(['status' => ApplicationStatus::Denied]);

        ApplicationStatusLog::create([
            'application_id' => $renewal->id,
            'from_status' => $from,
            'to_status' => ApplicationStatus::Denied,
            'changed_by' => $staff->id,
            'reason' => $reason,
        ]);
    }
}
