<?php

namespace App\Enums;

enum AcceptanceStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return str($this->value)->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Declined, self::Forfeited => 'border-crimson-600 text-crimson-600',
            self::Pending => 'border-amber-600 text-amber-600',
            self::Accepted => 'border-forest-600 text-forest-700',
        };
    }
}
