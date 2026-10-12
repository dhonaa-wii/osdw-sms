<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One applicant's submission against one additional requirement — the
 * uploaded file and its review, and/or the physical copy staff received.
 */
class ApplicationAdditionalRequirement extends Model
{
    use Auditable;

    protected $fillable = [
        'application_id',
        'offering_additional_requirement_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'submitted_at',
        'status',
        'remarks',
        'verified_by',
        'verified_at',
        'physical_received_at',
        'physical_received_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'physical_received_at' => 'datetime',
        ];
    }

    /**
     * Complied means every part the requirement asks for is in: the upload
     * verified (if it needs one) and the physical copy received (if it
     * needs one).
     */
    public function isComplied(): bool
    {
        $mode = $this->requirement->submission_mode;

        if ($mode->needsUpload() && ($this->file_path === null || $this->status !== DocumentStatus::Verified)) {
            return false;
        }

        return ! $mode->needsPhysical() || $this->physical_received_at !== null;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(OfferingAdditionalRequirement::class, 'offering_additional_requirement_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
