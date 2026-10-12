<?php

namespace App\Services;

use App\Enums\AllocationMode;
use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\CollegeScope;
use App\Enums\RequirementStage;
use App\Models\College;
use App\Models\OfferingCollege;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OfferingService
{
    public function __construct(
        protected AwardService $awardService,
        protected StudentNotificationService $notifications,
    ) {}

    /**
     * Statuses that mean an applicant holds a slot in this offering. A denied
     * application never does.
     *
     * @var list<string>
     */
    protected array $awardedStatuses = ['awarded'];

    /**
     * Headline figures for one offering: the money it commits, how many
     * applied, and how many hold a slot — overall and per college.
     *
     * Applications still in `draft` are excluded from every count: the
     * student has not submitted them, so they are not applicants yet.
     *
     * Renewals are counted on their own — they are scholars continuing, not
     * applicants, and they take no slot. The allocation covers the new
     * slots plus every renewal opened into the offering that is not denied.
     *
     * @return array{applied: int, awarded: int, renewals: int, renewed: int, allocation: float, awardedAmount: float, perCollege: Collection<int, array{college: College, applied: int, awarded: int, slots: int|null}>}
     */
    public function summary(ProgramOffering $offering): array
    {
        $renewals = $offering->applications()
            ->where('kind', ApplicationKind::Renewal->value)
            ->get(['id', 'status']);

        $applications = $offering->applications()
            ->where('kind', ApplicationKind::New->value)
            ->where('status', '!=', 'draft')
            ->with('college')
            ->get(['id', 'college_id', 'status']);

        $applied = $applications->count();
        $awarded = $applications->whereIn('status.value', $this->awardedStatuses)->count();

        // Quota colleges appear even with no applicants yet — an empty quota
        // is exactly what staff need to see.
        $quotaSlots = $offering->offeringColleges()->with('college')->get();

        $perCollege = $quotaSlots
            ->map(fn (OfferingCollege $offeringCollege) => $offeringCollege->college)
            ->merge($applications->pluck('college'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn ($college) => [
                'college' => $college,
                'applied' => $applications->where('college_id', $college->id)->count(),
                'awarded' => $applications->where('college_id', $college->id)
                    ->whereIn('status.value', $this->awardedStatuses)
                    ->count(),
                'slots' => $quotaSlots->firstWhere('college_id', $college->id)?->slots,
            ])
            ->values();

        return [
            'applied' => $applied,
            'awarded' => $awarded,
            'renewals' => $renewals->count(),
            'renewed' => $renewals->whereIn('status.value', $this->awardedStatuses)->count(),
            'allocation' => (float) $offering->amount * ($offering->total_slots + $renewals->reject(fn ($renewal) => $renewal->status === ApplicationStatus::Denied)->count()),
            'awardedAmount' => (float) $offering->awards()->sum('amount'),
            'perCollege' => $perCollege,
        ];
    }

    /**
     * Open a new offering of a scholarship program.
     *
     * The offering starts with no form fields, eligibility rules or
     * requirements of its own — staff configure all of it directly on the
     * offering. Everything that can change from term to term — college
     * scope, the eligible colleges, allocation mode, slots, dates, amount —
     * is set on the offering itself.
     *
     * @param  array<string, mixed>  $attributes  term, campus, dates, slots, amount, scope, allocation
     */
    public function openOffering(ScholarshipProgram $program, array $attributes): ProgramOffering
    {
        return DB::transaction(fn () => $program->offerings()->create([
            ...$attributes,
            'college_scope' => $attributes['college_scope'] ?? CollegeScope::All->value,
            'allocation_mode' => $attributes['allocation_mode'] ?? AllocationMode::Open->value,
        ]));
    }

    /**
     * Publish an offering's awarded list to the public site. Only allowed
     * once every slot is awarded, so this always marks a round as finished
     * rather than a partial snapshot of one still in progress.
     */
    public function publishResults(ProgramOffering $offering, string $instructions): void
    {
        if ($this->awardService->remainingSlots($offering) > 0) {
            throw new \RuntimeException('All slots must be awarded before results can be published.');
        }

        $firstPublish = $offering->results_published_at === null;

        $offering->update([
            'results_published' => true,
            'publish_instructions' => $instructions,
            'results_published_at' => $offering->results_published_at ?? now(),
        ]);

        if ($firstPublish) {
            $this->notifications->resultsPublished($offering);
        }
    }

    /**
     * Pull a published list back off the public site. The instructions text
     * is kept so re-publishing doesn't make staff retype it.
     */
    public function unpublishResults(ProgramOffering $offering): void
    {
        $offering->update(['results_published' => false]);
    }

    /**
     * New applicants on the shortlist or already awarded. The shortlist may
     * hold more applicants than there are slots.
     */
    public function shortlistedCount(ProgramOffering $offering): int
    {
        return $offering->applications()
            ->where('kind', ApplicationKind::New->value)
            ->whereIn('status', [ApplicationStatus::Shortlisted->value, ApplicationStatus::Awarded->value])
            ->count();
    }

    public function shortlistFillsSlots(ProgramOffering $offering): bool
    {
        return $this->shortlistedCount($offering) >= $offering->total_slots;
    }

    /**
     * Let shortlisted applicants see and submit the shortlist requirements.
     * Allowed once the shortlist holds at least as many applicants as there
     * are slots; applicants shortlisted later see them straight away.
     */
    public function openShortlistRequirements(ProgramOffering $offering): void
    {
        if (! $this->shortlistFillsSlots($offering)) {
            throw new \RuntimeException('Shortlist at least as many applicants as there are slots first.');
        }

        if ($offering->shortlist_requirements_opened_at !== null) {
            return;
        }

        $offering->update(['shortlist_requirements_opened_at' => now()]);
        $this->notifications->requirementsOpened($offering, RequirementStage::Shortlist);
    }

    /**
     * Let awardees see and submit the disbursement requirements. Allowed
     * once every slot is awarded.
     */
    public function openAwardRequirements(ProgramOffering $offering): void
    {
        if ($this->awardService->remainingSlots($offering) > 0) {
            throw new \RuntimeException('Award every slot before opening disbursement requirements.');
        }

        if ($offering->award_requirements_opened_at !== null) {
            return;
        }

        $offering->update(['award_requirements_opened_at' => now()]);
        $this->notifications->requirementsOpened($offering, RequirementStage::Award);
    }
}
