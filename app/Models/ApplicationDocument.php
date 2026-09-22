<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    use Auditable;

    protected $fillable = [
        'application_id',
        'offering_form_field_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'physical_received',
        'verified_by',
        'verified_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'physical_received' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function offeringFormField(): BelongsTo
    {
        return $this->belongsTo(OfferingFormField::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
