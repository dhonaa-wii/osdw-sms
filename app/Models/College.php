<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function offeringColleges(): HasMany
    {
        return $this->hasMany(OfferingCollege::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
