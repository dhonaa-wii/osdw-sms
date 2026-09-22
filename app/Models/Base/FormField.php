<?php

namespace App\Models\Base;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared shape for the two form-field tables. A scholarship program holds the
 * template; creating an offering copies those rows into the offering's own table,
 * where they can be edited without touching the template or any other offering.
 *
 * Both sides must stay column-for-column identical — the copy in
 * OfferingService reads one and writes the other.
 */
abstract class FormField extends Model
{
    protected const SHARED_FILLABLE = [
        'field_type',
        'label',
        'description',
        'options',
        'is_required',
        'requires_physical_copy',
        'accepted_formats',
        'max_size_mb',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => FieldType::class,
            'is_required' => 'boolean',
            'requires_physical_copy' => 'boolean',
        ];
    }

    public function optionList(): array
    {
        return $this->options
            ? array_values(array_filter(array_map('trim', explode("\n", $this->options))))
            : [];
    }

    /**
     * The attributes carried from a program template row to an offering row, or
     * the reverse. Excludes the owner key, which the caller sets.
     */
    public function copyableAttributes(): array
    {
        return $this->only(self::SHARED_FILLABLE);
    }

    abstract public function owner(): BelongsTo;
}
