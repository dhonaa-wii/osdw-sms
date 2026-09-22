<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingCollege extends Model
{
    protected $fillable = [
        'program_offering_id',
        'college_id',
        'slots',
    ];

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
