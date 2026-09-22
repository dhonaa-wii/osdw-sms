<?php

namespace App\Models;

use App\Enums\ScholarshipCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scholarship a provider offers. Holds the configuration template that
 * every new offering starts from — form fields, eligibility and retention
 * rules. Anything that can change from term to term (college scope, the
 * eligible colleges, allocation mode, slots, dates, amount) belongs to the
 * offering instead.
 */
class ScholarshipProgram extends Model
{
    protected $fillable = [
        'name',
        'provider_id',
        'category',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => ScholarshipCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(ProgramOffering::class);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(ProgramFormField::class);
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(ProgramEligibilityRule::class);
    }

    public function retentionRules(): HasMany
    {
        return $this->hasMany(ProgramRetentionRule::class);
    }
}
