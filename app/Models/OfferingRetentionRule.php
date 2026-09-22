<?php

namespace App\Models;

use App\Models\Base\RetentionRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingRetentionRule extends RetentionRule
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
}
