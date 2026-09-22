<?php

namespace App\Enums;

enum AwardStatus: string
{
    case Reserved = 'reserved';
    case Active = 'active';
    case Terminated = 'terminated';
    case Completed = 'completed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Terminated => 'border-crimson-600 text-crimson-600',
            self::Reserved => 'border-amber-600 text-amber-600',
            self::Active, self::Completed => 'border-forest-600 text-forest-700',
        };
    }
}
