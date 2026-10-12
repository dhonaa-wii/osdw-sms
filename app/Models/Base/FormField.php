<?php

namespace App\Models\Base;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared shape for the offering and standard form-field tables.
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
}
