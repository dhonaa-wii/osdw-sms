<?php

namespace App\Models;

use App\Enums\AllocationMode;
use App\Enums\ApplicationKind;
use App\Enums\CollegeScope;
use App\Enums\OfferingStatus;
use App\Enums\RequirementSource;
use App\Enums\RequirementStage;
use App\Enums\ScholarshipCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One run of a scholarship program in a given term, named by the admin —
 * "Batch 1", "First semester intake". Configures its own form fields and
 * eligibility rules from scratch — editing one offering never affects a
 * sibling offering. College scope and the eligible colleges are set here
 * too, as are the additional requirements (shortlist or award stage) and
 * the interview schedule.
 *
 * A renewal offering (`renews_offering_id` set) continues an earlier
 * offering of the same program: it takes only that offering's awardees, who
 * hand in its renewal requirements instead of applying. With
 * `accepts_new_applicants` on it also takes new applicants into its slots,
 * the same way a first offering does.
 *
 * Deleting one soft-deletes it (DeletionService); it can be restored from
 * Settings → Deleted items until it is purged.
 */
class ProgramOffering extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'scholarship_program_id',
        'renews_offering_id',
        'accepts_new_applicants',
        'requirements_source',
        'academic_term_id',
        'name',
        'category',
        'campus_id',
        'opens_at',
        'closes_at',
        'total_slots',
        'max_applications',
        'college_scope',
        'allocation_mode',
        'amount',
        'results_published',
        'publish_instructions',
        'results_published_at',
        'has_interview',
        'interview_at',
        'interview_venue',
        'interview_instructions',
        'shortlist_requirements_opened_at',
        'award_requirements_opened_at',
        'registration_opened_notified_at',
        'registration_closed_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'accepts_new_applicants' => 'boolean',
            'requirements_source' => RequirementSource::class,
            'category' => ScholarshipCategory::class,
            'college_scope' => CollegeScope::class,
            'allocation_mode' => AllocationMode::class,
            'amount' => 'decimal:2',
            'results_published' => 'boolean',
            'results_published_at' => 'datetime',
            'has_interview' => 'boolean',
            'interview_at' => 'datetime',
            'shortlist_requirements_opened_at' => 'datetime',
            'award_requirements_opened_at' => 'datetime',
            'registration_opened_notified_at' => 'datetime',
            'registration_closed_notified_at' => 'datetime',
        ];
    }

    /**
     * What staff and students see this offering called. Offerings opened
     * before naming existed fall back to their term.
     */
    public function displayName(): string
    {
        return $this->name ?: $this->academicTerm->school_year.' · '.$this->academicTerm->semester->label();
    }

    /**
     * Registration is open only within the application window and (when a
     * cap is set) while applications remain under it. There is no manually
     * configured status field — this is always derived from live data.
     * A renewal offering is open only when it also takes new applicants.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('renews_offering_id')->orWhere('accepts_new_applicants', true))
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->where(function (Builder $query) {
                $query->whereNull('max_applications')
                    ->orWhereRaw(
                        "(select count(*) from applications where applications.program_offering_id = program_offerings.id and applications.kind = 'new') < program_offerings.max_applications"
                    );
            });
    }

    public function registrationStatus(): OfferingStatus
    {
        $now = now();

        if ($now->lt($this->opens_at)) {
            return OfferingStatus::Upcoming;
        }

        if ($now->gte($this->closes_at)) {
            return OfferingStatus::Closed;
        }

        if ($this->hasReachedApplicationCap()) {
            return OfferingStatus::Closed;
        }

        return OfferingStatus::Open;
    }

    public function hasReachedApplicationCap(): bool
    {
        if ($this->max_applications === null) {
            return false;
        }

        // Renewals are not applicants, so they never count against the cap.
        $count = $this->applications()->where('kind', ApplicationKind::New->value)->count();

        return $count >= $this->max_applications;
    }

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function offeringColleges(): HasMany
    {
        return $this->hasMany(OfferingCollege::class);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(OfferingFormField::class);
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(OfferingEligibilityRule::class);
    }

    /**
     * The earlier offering whose awardees this one renews.
     */
    public function renewsOffering(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renews_offering_id');
    }

    /**
     * Later offerings that renew this one's awardees.
     */
    public function renewalOfferings(): HasMany
    {
        return $this->hasMany(self::class, 'renews_offering_id');
    }

    public function isRenewal(): bool
    {
        return $this->renews_offering_id !== null;
    }

    /**
     * Whether students can apply here: every first offering, and a renewal
     * offering the admin opened to new applicants.
     */
    public function takesNewApplicants(): bool
    {
        return ! $this->isRenewal() || $this->accepts_new_applicants;
    }

    /**
     * On a renewal offering, opens_at and closes_at are the window in which
     * scholars upload their renewal requirements.
     */
    public function acceptsRenewalSubmissions(): bool
    {
        return $this->isRenewal() && now()->between($this->opens_at, $this->closes_at);
    }

    /**
     * Whether the application form and disbursement requirements are a copy
     * of the standard requirements rather than set on this offering by hand.
     */
    public function usesStandardRequirements(): bool
    {
        return $this->takesNewApplicants() && $this->requirements_source === RequirementSource::Standard;
    }

    public function additionalRequirements(): HasMany
    {
        return $this->hasMany(OfferingAdditionalRequirement::class)->orderBy('sort_order');
    }

    /**
     * The interview notice shows on a shortlisted applicant's tracking page
     * only once the offering asks for an interview and a date is set.
     */
    public function hasScheduledInterview(): bool
    {
        return $this->has_interview && $this->interview_at !== null;
    }

    /**
     * Whether applicants can see this stage's requirements yet. Staff open
     * shortlist requirements from the Shortlist tab and disbursement ones
     * from the Grantees page; renewal requirements follow the submission
     * window instead.
     */
    public function hasOpenedRequirements(RequirementStage $stage): bool
    {
        return match ($stage) {
            RequirementStage::Shortlist => $this->shortlist_requirements_opened_at !== null,
            RequirementStage::Award => $this->award_requirements_opened_at !== null,
            RequirementStage::Renewal => true,
        };
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
