<?php

namespace App\Models;

use App\Enums\Sex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    protected $fillable = [
        'student_id',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'sex',
        'birthdate',
        'email',
        'mobile',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'birthdate' => 'date',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
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
