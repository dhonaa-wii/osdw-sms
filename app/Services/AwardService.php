<?php

namespace App\Services;

use App\Enums\AllocationMode;
use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\EmailType;
use App\Enums\RequirementStage;
use App\Mail\AwardNoticeMail;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Award;
use App\Models\ProgramOffering;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AwardService
{
    public function __construct(
        protected EmailService $emailService,
        protected OfferingRequirementService $requirementService,
        protected StudentNotificationService $studentNotifications,
        protected ScholarshipHoldingService $holdings,
    ) {}

    /**
     * Slots already spent for an offering, or for one college under quota
     * allocation. An award is permanent once created, so every row counts.
     *
     * Slots are for new applicants only — every scholar still enrolled
     * renews without taking one — so the count is always for one kind.
     */
    public function usedSlots(ProgramOffering $offering, ?int $collegeId = null, ApplicationKind $kind = ApplicationKind::New): int
    {
        return Award::where('program_offering_id', $offering->id)
            ->whereHas('application', fn ($q) => $q
                ->where('kind', $kind->value)
                ->when($collegeId !== null, fn ($q) => $q->where('college_id', $collegeId)))
            ->count();
    }

    public function remainingSlots(ProgramOffering $offering, ?int $collegeId = null): int
    {
        $total = $collegeId !== null
            ? $offering->offeringColleges->firstWhere('college_id', $collegeId)?->slots ?? 0
            : $offering->total_slots;

        return max(0, $total - $this->usedSlots($offering, $collegeId));
    }

    /**
     * Award a shortlisted applicant, or renew a scholar under review. A
     * renewal skips the shortlist and takes no slot; its renewal requirements
     * gate it the way shortlist requirements gate an award.
     */
    public function create(Application $application, User $staff, ?string $overrideReason = null): Award
    {
        $offering = $application->programOffering;

        if ($application->isRenewal()) {
            if ($application->status !== ApplicationStatus::Screening) {
                throw new \RuntimeException('This renewal is already decided.');
            }

            if ($application->year_level === null) {
                throw new \RuntimeException('This scholar has not entered their year level yet.');
            }

            if ($this->requirementService->outstandingCount($application, RequirementStage::Renewal) > 0) {
                throw new \RuntimeException('This scholar still has renewal requirements to comply with.');
            }
        } else {
            $collegeId = $offering->allocation_mode === AllocationMode::Quota ? $application->college_id : null;

            if ($this->remainingSlots($offering, $collegeId) <= 0) {
                throw new \RuntimeException('No slots remaining for this offering.');
            }

            if ($this->requirementService->outstandingCount($application, RequirementStage::Shortlist) > 0) {
                throw new \RuntimeException('This applicant still has shortlist requirements to comply with.');
            }

            if ($blocker = $this->holdings->awardBlocker($application)) {
                throw new \RuntimeException($blocker);
            }
        }

        $award = DB::transaction(function () use ($application, $offering, $staff, $overrideReason) {
            $award = Award::create([
                'application_id' => $application->id,
                'applicant_id' => $application->applicant_id,
                'program_offering_id' => $offering->id,
                'academic_term_id' => $offering->academic_term_id,
                'amount' => $offering->amount,
                'awarded_by' => $staff->id,
                'awarded_at' => now(),
                'is_override' => $overrideReason !== null,
                'override_reason' => $overrideReason,
            ]);

            $this->transitionApplication($application, 'awarded', $staff, $overrideReason);

            return $award;
        });

        $this->emailService->send(
            $application,
            EmailType::AwardNotice,
            new AwardNoticeMail($award),
            $application->applicant->email,
        );

        return $award;
    }

    /**
     * Why an award can't be recorded as disbursed yet, or null when it can.
     * A new award waits for every disbursement requirement to be complied —
     * uploads verified, physical copies received — so nothing is left for
     * staff to review. A renewal was already gated by its renewal
     * requirements, so it can be disbursed once renewed.
     */
    public function disbursementBlocker(Award $award): ?string
    {
        if ($award->isDisbursed()) {
            return 'This award is already disbursed.';
        }

        if (! $award->application->isRenewal()
            && $this->requirementService->outstandingCount($award->application, RequirementStage::Award) > 0) {
            return 'This grantee still has disbursement requirements to comply with or review.';
        }

        return null;
    }

    /**
     * Record that the award's fund was released to the grantee. The system
     * only records it; the application stays awarded and reads Disbursed.
     */
    public function disburse(Award $award, User $staff): void
    {
        if ($blocker = $this->disbursementBlocker($award)) {
            throw new \RuntimeException($blocker);
        }

        $award->update(['disbursed_at' => now(), 'disbursed_by' => $staff->id]);

        $this->studentNotifications->disbursed($award);
    }

    /**
     * Why an offering's awards can't all be disbursed at once, or null when
     * they can: every slot awarded, at least one award left to disburse,
     * and every one of them ready.
     */
    public function disburseAllBlocker(ProgramOffering $offering): ?string
    {
        if ($offering->takesNewApplicants() && $this->remainingSlots($offering) > 0) {
            return 'Award every slot first.';
        }

        $pending = $this->undisbursedAwards($offering);

        if ($pending->isEmpty()) {
            return 'Every grantee in this offering is already disbursed.';
        }

        $notReady = $pending->filter(fn (Award $award) => $this->disbursementBlocker($award) !== null)->count();

        return $notReady > 0
            ? $notReady.' '.str('grantee')->plural($notReady).' still '.($notReady === 1 ? 'has' : 'have').' disbursement requirements to comply with or review.'
            : null;
    }

    /**
     * Disburse every remaining award of an offering in one go. Returns how
     * many were disbursed.
     */
    public function disburseAll(ProgramOffering $offering, User $staff): int
    {
        if ($blocker = $this->disburseAllBlocker($offering)) {
            throw new \RuntimeException($blocker);
        }

        $awards = DB::transaction(function () use ($offering, $staff) {
            $awards = $this->undisbursedAwards($offering);

            // One update per award so each lands in the audit log.
            $awards->each(fn (Award $award) => $award->update(['disbursed_at' => now(), 'disbursed_by' => $staff->id]));

            return $awards;
        });

        $awards->each(fn (Award $award) => $this->studentNotifications->disbursed($award));

        return $awards->count();
    }

    /** @return Collection<int, Award> */
    protected function undisbursedAwards(ProgramOffering $offering)
    {
        return Award::where('program_offering_id', $offering->id)
            ->whereNull('disbursed_at')
            ->with('application.programOffering')
            ->get();
    }

    protected function transitionApplication(Application $application, string $toStatus, ?User $changedBy, ?string $reason): void
    {
        $from = $application->status;
        $application->update(['status' => $toStatus]);

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'changed_by' => $changedBy?->id,
            'reason' => $reason,
        ]);
    }
}
