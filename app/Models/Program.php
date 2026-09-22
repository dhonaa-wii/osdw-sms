<?php

namespace App\Models;

use App\Enums\DegreeLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = [
        'college_id',
        'code',
        'name',
        'degree_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'degree_level' => DegreeLevel::class,
            'is_active' => 'boolean',
        ];
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
