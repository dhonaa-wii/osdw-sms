<?php

namespace App\Models;

use App\Enums\RequirementStage;
use App\Enums\SubmissionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something an offering asks for beyond its application form — at the
 * shortlist stage (gates the award) or after the award (for disbursement).
 * Configured on the offering's Requirements page; never shared with a
 * sibling offering.
 */
class OfferingAdditionalRequirement extends Model
{
    protected $fillable = [
        'program_offering_id',
        'stage',
        'label',
        'instructions',
        'submission_mode',
        'accepted_formats',
        'max_size_mb',
        'template_path',
        'template_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stage' => RequirementStage::class,
            'submission_mode' => SubmissionMode::class,
            'max_size_mb' => 'integer',
        ];
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ApplicationAdditionalRequirement::class);
    }
}
