<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFieldResponse extends Model
{
    protected $fillable = [
        'application_id',
        'offering_form_field_id',
        'value',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function offeringFormField(): BelongsTo
    {
        return $this->belongsTo(OfferingFormField::class);
    }
}
