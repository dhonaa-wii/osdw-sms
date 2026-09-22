<?php

namespace App\Enums;

enum EligibilityOperator: string
{
    case Lte = 'lte';
    case Gte = 'gte';
    case Eq = 'eq';
    case In = 'in';

    public function label(): string
    {
        return match ($this) {
            self::Lte => 'At most',
            self::Gte => 'At least',
            self::Eq => 'Equal to',
            self::In => 'One of',
        };
    }
}
