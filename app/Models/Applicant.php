<?php

namespace App\Models;

use App\Enums\Sex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    protected $fillable = [
        'user_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])));
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }
}
