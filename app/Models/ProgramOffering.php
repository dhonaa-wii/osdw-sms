<?php

namespace App\Models;

use App\Enums\AllocationMode;
use App\Enums\CollegeScope;
use App\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of a scholarship program in a given term, named by the admin —
 * "Batch 1", "First semester intake". Created by copying the program's
 * configuration (form fields, rules), after which the offering owns that copy
 * outright — editing it never affects the program template or any sibling
 * offering. College scope and the eligible colleges are set here, not copied.
 */
class ProgramOffering extends Model
{
    protected $fillable = [
        'scholarship_program_id',
        'academic_term_id',
        'name',
        'campus_id',
        'opens_at',
        'closes_at',
        'total_slots',
        'max_applications',
        'college_scope',
        'allocation_mode',
        'amount',
        'discount_rate',
        'results_published',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'college_scope' => CollegeScope::class,
            'allocation_mode' => AllocationMode::class,
            'amount' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'results_published' => 'boolean',
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
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->where(function (Builder $query) {
                $query->whereNull('max_applications')
                    ->orWhereRaw(
                        '(select count(*) from applications where applications.program_offering_id = program_offerings.id) < program_offerings.max_applications'
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

        $count = $this->applications_count ?? $this->applications()->count();

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

    public function retentionRules(): HasMany
    {
        return $this->hasMany(OfferingRetentionRule::class);
    }

    public function additionalRequirements(): HasMany
    {
        return $this->hasMany(OfferingAdditionalRequirement::class);
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
