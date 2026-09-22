<?php

namespace App\Models;

use App\Models\Base\EligibilityRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferingEligibilityRule extends EligibilityRule
{
    protected $fillable = [...self::SHARED_FILLABLE, 'program_offering_id'];

    public function owner(): BelongsTo
    {
        return $this->programOffering();
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(ApplicationScreening::class);
    }
}
