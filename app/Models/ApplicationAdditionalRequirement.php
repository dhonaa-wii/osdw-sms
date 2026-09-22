<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One applicant's receipt for one of the offering's additional requirements.
 * The row exists only once the item has been handed in — no row means the
 * applicant still owes it.
 */
class ApplicationAdditionalRequirement extends Model
{
    use Auditable;

    protected $fillable = [
        'application_id',
        'offering_additional_requirement_id',
        'received_at',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function offeringAdditionalRequirement(): BelongsTo
    {
        return $this->belongsTo(OfferingAdditionalRequirement::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
