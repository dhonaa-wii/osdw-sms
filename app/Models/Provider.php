<?php

namespace App\Models;

use App\Enums\FundingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $fillable = [
        'name',
        'funding_source',
        'contact_person',
        'contact_email',
        'allocated_budget',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'funding_source' => FundingSource::class,
            'allocated_budget' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scholarshipPrograms(): HasMany
    {
        return $this->hasMany(ScholarshipProgram::class);
    }
}
