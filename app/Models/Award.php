<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    use Auditable;

    protected $fillable = [
        'application_id',
        'applicant_id',
        'program_offering_id',
        'academic_term_id',
        'amount',
        'awarded_by',
        'awarded_at',
        'disbursed_at',
        'disbursed_by',
        'is_override',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'awarded_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'is_override' => 'boolean',
        ];
    }

    public function isDisbursed(): bool
    {
        return $this->disbursed_at !== null;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }
}
