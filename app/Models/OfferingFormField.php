<?php

namespace App\Models;

use App\Models\Base\FormField;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferingFormField extends FormField
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

    public function applicationDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function applicationFieldResponses(): HasMany
    {
        return $this->hasMany(ApplicationFieldResponse::class);
    }

    /**
     * A field that applicants have already answered cannot be removed or have
     * its type changed — the stored documents and responses point at it.
     */
    public function hasResponses(): bool
    {
        return $this->applicationDocuments()->exists()
            || $this->applicationFieldResponses()->exists();
    }
}
