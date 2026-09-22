<?php

namespace App\Enums;

enum OfferingStatus: string
{
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return str($this->value)->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Upcoming => 'border-mist text-mist',
            self::Open => 'border-forest-600 text-forest-700',
            self::Closed => 'border-slate text-slate',
        };
    }
}
