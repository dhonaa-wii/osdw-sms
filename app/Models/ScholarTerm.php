<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ScholarTermStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarTerm extends Model
{
    use Auditable;

    protected $fillable = [
        'award_id',
        'applicant_id',
        'academic_term_id',
        'gwa',
        'units_enrolled',
        'has_failing_grade',
        'status',
        'remarks',
        'evaluated_by',
    ];

    protected function casts(): array
    {
        return [
            'gwa' => 'decimal:2',
            'has_failing_grade' => 'boolean',
            'status' => ScholarTermStatus::class,
        ];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
