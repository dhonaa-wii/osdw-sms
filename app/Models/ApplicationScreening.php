<?php

namespace App\Models;

use App\Enums\CheckType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationScreening extends Model
{
    protected $fillable = [
        'application_id',
        'offering_eligibility_rule_id',
        'check_type',
        'passed',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'check_type' => CheckType::class,
            'passed' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function eligibilityRule(): BelongsTo
    {
        return $this->belongsTo(OfferingEligibilityRule::class);
    }
}
