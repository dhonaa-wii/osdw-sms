<?php

namespace App\Models;

use App\Models\Base\FormField;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramFormField extends FormField
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
