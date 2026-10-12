<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use Auditable;

    protected $fillable = [
        'applicant_id',
        'program_offering_id',
        'kind',
        'renewed_from_award_id',
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
            'kind' => ApplicationKind::class,
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

    /**
     * Students see only their own applications and files.
     */
    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->applicant()->where('user_id', $user->id)->exists();
    }

    public function isRenewal(): bool
    {
        return $this->kind === ApplicationKind::Renewal;
    }

    /**
     * The award in the previous offering this renewal continues.
     */
    public function renewedFromAward(): BelongsTo
    {
        return $this->belongsTo(Award::class, 'renewed_from_award_id');
    }

    /**
     * What the status stamp reads. A renewal never passes through the
     * shortlist, so its review and award stages get their own names. An
     * awardee whose fund was released reads Disbursed; the application
     * itself stays awarded.
     */
    public function statusLabel(?ApplicationStatus $status = null): string
    {
        if ($status === null && $this->isDisbursed()) {
            return 'Disbursed';
        }

        $status ??= $this->status;

        if (! $this->isRenewal()) {
            return $status->label();
        }

        return match ($status) {
            ApplicationStatus::Screening => 'Under review',
            ApplicationStatus::Awarded => 'Renewed',
            default => $status->label(),
        };
    }

    public function isDisbursed(): bool
    {
        return $this->status === ApplicationStatus::Awarded && (bool) $this->award?->isDisbursed();
    }

    public function statusStampClasses(): string
    {
        return $this->status->stampClasses();
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

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }

    public function additionalRequirementSubmissions(): HasMany
    {
        return $this->hasMany(ApplicationAdditionalRequirement::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Multi-application flag color/label per the doc spec — red for an
     * award, amber for a still-pending outcome, gray for anything closed
     * out (denied).
     *
     * @return array{color: string, label: string}
     */
    public function outcomeBadge(): array
    {
        if ($this->award) {
            return ['color' => 'crimson-600', 'label' => 'Awarded'];
        }

        if (in_array($this->status->value, ['submitted', 'screening', 'shortlisted', 'waitlisted'], true)) {
            return ['color' => 'amber-600', 'label' => 'Pending'];
        }

        return ['color' => 'mist', 'label' => 'History'];
    }
}
