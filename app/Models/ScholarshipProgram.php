<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A scholarship a provider offers — its identity (name, provider,
 * description). Every offering configures its own form fields, eligibility
 * rules, requirements, and everything else that can change from term to
 * term (category, college scope, eligible colleges, allocation mode, slots,
 * dates, amount); none of it lives on the program.
 *
 * Deleting one soft-deletes it together with its offerings
 * (DeletionService); it can be restored from Settings → Deleted items until
 * it is purged.
 */
class ScholarshipProgram extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'provider_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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
}
