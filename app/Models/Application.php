<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Application extends Model
{
    use Auditable;

    protected $fillable = [
        'reference_no',
        'access_token',
        'applicant_id',
        'program_offering_id',
        'college_id',
        'year_level',
        'gwa',
        'monthly_income',
        'consent_publish',
        'status',
        'submitted_at',
        'screened_at',
    ];

    protected function casts(): array
    {
        return [
            'gwa' => 'decimal:2',
            'monthly_income' => 'decimal:2',
            'consent_publish' => 'boolean',
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'screened_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function fieldResponses(): HasMany
    {
        return $this->hasMany(ApplicationFieldResponse::class);
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(ApplicationScreening::class);
    }

    public function additionalRequirementReceipts(): HasMany
    {
        return $this->hasMany(ApplicationAdditionalRequirement::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * The offering's additional requirements this applicant has not handed in
     * yet. While any remain, the applicant cannot be awarded.
     *
     * @return Collection<int, OfferingAdditionalRequirement>
     */
    public function outstandingAdditionalRequirements(): Collection
    {
        $received = $this->additionalRequirementReceipts->pluck('offering_additional_requirement_id');

        return $this->programOffering->additionalRequirements
            ->reject(fn (OfferingAdditionalRequirement $requirement) => $received->contains($requirement->id))
            ->values();
    }

    public function hasReceivedAdditionalRequirement(OfferingAdditionalRequirement $requirement): bool
    {
        return $this->additionalRequirementReceipts
            ->contains('offering_additional_requirement_id', $requirement->id);
    }

    /**
     * Multi-application flag color/label per the doc spec — red for an
     * active award, amber for a still-pending outcome, gray for anything
     * closed out (denied, withdrawn, declined, terminated).
     *
     * @return array{color: string, label: string}
     */
    public function outcomeBadge(): array
    {
        $award = $this->award;

        if ($award && $award->acceptance_status->value === 'accepted' && in_array($award->status->value, ['active', 'reserved'], true)) {
            return ['color' => 'crimson-600', 'label' => 'Active award'];
        }

        if ($award && $award->acceptance_status->value === 'pending') {
            return ['color' => 'amber-600', 'label' => 'Award pending acceptance'];
        }

        if (in_array($this->status->value, ['submitted', 'screening', 'shortlisted', 'approved', 'waitlisted'], true)) {
            return ['color' => 'amber-600', 'label' => 'Pending'];
        }

        return ['color' => 'mist', 'label' => 'History'];
    }
}
