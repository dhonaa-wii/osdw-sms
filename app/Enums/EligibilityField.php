<?php

namespace App\Enums;

enum EligibilityField: string
{
    case Gwa = 'gwa';
    case YearLevel = 'year_level';
    case College = 'college';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Gwa => 'GWA',
            self::Income => 'Family monthly income',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }
}
