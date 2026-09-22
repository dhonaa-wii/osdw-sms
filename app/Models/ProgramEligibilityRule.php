<?php

namespace App\Models;

use App\Models\Base\EligibilityRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEligibilityRule extends EligibilityRule
{
    protected $fillable = [...self::SHARED_FILLABLE, 'scholarship_program_id'];

    public function owner(): BelongsTo
    {
        return $this->scholarshipProgram();
    }

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
    }
}
