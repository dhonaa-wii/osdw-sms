<?php

namespace App\Models\Base;

use App\Enums\EligibilityOperator;
use App\Enums\RetentionField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared shape for the two retention-rule tables — program template and offering
 * working copy. See Base\FormField for why the pair exists.
 */
abstract class RetentionRule extends Model
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
            'field' => RetentionField::class,
            'operator' => EligibilityOperator::class,
        ];
    }

    public function describe(): string
    {
        return "{$this->field->label()} must be {$this->operator->label()} {$this->value}";
    }

    public function copyableAttributes(): array
    {
        return $this->only(self::SHARED_FILLABLE);
    }

    abstract public function owner(): BelongsTo;
}
