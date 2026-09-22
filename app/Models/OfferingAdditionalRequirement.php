<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\RequirementSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An item staff ask every shortlisted applicant of one offering for, on top of
 * the offering's own form fields — a hard copy the provider wants, an affidavit
 * the agency asked for late. Set once per offering; each applicant is then
 * ticked off individually as their copy comes in.
 */
class OfferingAdditionalRequirement extends Model
{
    use Auditable;

    protected $fillable = [
        'program_offering_id',
        'label',
        'submission_mode',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'submission_mode' => RequirementSubmission::class,
        ];
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ApplicationAdditionalRequirement::class);
    }
}
