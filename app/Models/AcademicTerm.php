<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    protected $fillable = [
        'school_year',
        'semester',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(ProgramOffering::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function scholarTerms(): HasMany
    {
        return $this->hasMany(ScholarTerm::class);
    }
}
