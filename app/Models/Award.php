<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\AcceptanceStatus;
use App\Enums\AwardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Award extends Model
{
    use Auditable;

    protected $fillable = [
        'application_id',
        'applicant_id',
        'program_offering_id',
        'academic_term_id',
        'amount',
        'discount_rate',
        'awarded_by',
        'awarded_at',
        'is_override',
        'override_reason',
        'acceptance_status',
        'accepted_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'awarded_at' => 'datetime',
            'is_override' => 'boolean',
            'acceptance_status' => AcceptanceStatus::class,
            'accepted_at' => 'datetime',
            'status' => AwardStatus::class,
        ];
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

    public function scholarTerms(): HasMany
    {
        return $this->hasMany(ScholarTerm::class);
    }
}
