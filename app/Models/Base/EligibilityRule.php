<?php

namespace App\Models\Base;

use App\Enums\EligibilityField;
use App\Enums\EligibilityOperator;
use App\Models\College;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared shape for the offering eligibility-rule table.
 */
abstract class EligibilityRule extends Model
{
    protected const SHARED_FILLABLE = [
        'field',
        'operator',
        'value',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'field' => EligibilityField::class,
            'operator' => EligibilityOperator::class,
        ];
    }

    public function describe(): string
    {
        if ($this->field === EligibilityField::College) {
            $ids = array_filter(explode(',', $this->value));
            $names = College::whereIn('id', $ids)->pluck('name')->implode(', ');

            return "{$this->field->label()} must be {$this->operator->label()}: {$names}";
        }

        return "{$this->field->label()} must be {$this->operator->label()} {$this->value}";
    }

    abstract public function owner(): BelongsTo;
}
