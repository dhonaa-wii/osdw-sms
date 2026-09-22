<?php

namespace App\Services;

use App\Enums\AllocationMode;
use App\Enums\EmailType;
use App\Mail\AwardNoticeMail;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Award;
use App\Models\ProgramOffering;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AwardService
{
    protected array $activeAwardStatuses = ['reserved', 'active'];

    public function __construct(protected EmailService $emailService) {}

    /**
     * Slots already spent for an offering, or for one college under quota
     * allocation. An award only "spends" a slot while it's live — declined
     * or terminated awards free it back up.
     */
    public function usedSlots(ProgramOffering $offering, ?int $collegeId = null): int
    {
        $query = Award::where('program_offering_id', $offering->id)
            ->whereIn('status', $this->activeAwardStatuses)
            ->whereNotIn('acceptance_status', ['declined', 'forfeited']);

        if ($collegeId !== null) {
            $query->whereHas('application', fn ($q) => $q->where('college_id', $collegeId));
        }

        return $query->count();
    }

    public function remainingSlots(ProgramOffering $offering, ?int $collegeId = null): int
    {
        $total = $collegeId !== null
            ? $offering->offeringColleges->firstWhere('college_id', $collegeId)?->slots ?? 0
            : $offering->total_slots;

        return max(0, $total - $this->usedSlots($offering, $collegeId));
    }

    public function conflictingAward(Application $application): ?Award
    {
        return Award::where('applicant_id', $application->applicant_id)
            ->where('id', '!=', $application->award?->id)
            ->whereIn('status', $this->activeAwardStatuses)
            ->whereNotIn('acceptance_status', ['declined', 'forfeited'])
            ->first();
    }

    public function create(Application $application, User $staff, ?string $overrideReason = null): Award
    {
        $offering = $application->programOffering;
        $collegeId = $offering->allocation_mode === AllocationMode::Quota ? $application->college_id : null;

        if ($this->remainingSlots($offering, $collegeId) <= 0) {
            throw new \RuntimeException('No slots remaining for this offering.');
        }

        // Enforced here rather than in each screen so no award path — the
        // shortlist, the review page, a future bulk action — can skip it.
        if ($application->outstandingAdditionalRequirements()->isNotEmpty()) {
            throw new \RuntimeException('This applicant still owes an additional requirement.');
        }

        $award = DB::transaction(function () use ($application, $offering, $staff, $overrideReason) {
            $award = Award::create([
                'application_id' => $application->id,
                'applicant_id' => $application->applicant_id,
                'program_offering_id' => $offering->id,
                'academic_term_id' => $offering->academic_term_id,
                'amount' => $offering->amount,
                'discount_rate' => $offering->discount_rate,
                'awarded_by' => $staff->id,
                'awarded_at' => now(),
                'is_override' => $overrideReason !== null,
                'override_reason' => $overrideReason,
                'acceptance_status' => 'pending',
                'status' => 'reserved',
            ]);

            $this->transitionApplication($application, 'approved', $staff, $overrideReason);

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
     * Compliance is established during screening — an application can't be
     * endorsed unless its documents are already verified. So the moment a
     * student accepts, the award is active; there's no separate confirmation
     * step for staff.
     */
    public function accept(Award $award): void
    {
        $award->update([
            'acceptance_status' => 'accepted',
            'accepted_at' => now(),
            'status' => 'active',
        ]);

        $this->transitionApplication($award->application, 'accepted', null, null);
    }

    public function decline(Award $award): void
    {
        $award->update(['acceptance_status' => 'declined']);

        $this->transitionApplication($award->application, 'withdrawn', null, 'Declined award.');
    }

    public function forfeit(Award $award, User $staff, string $reason): void
    {
        $award->update(['status' => 'terminated', 'acceptance_status' => 'forfeited']);
        $this->transitionApplication($award->application, 'forfeited', $staff, $reason);
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
