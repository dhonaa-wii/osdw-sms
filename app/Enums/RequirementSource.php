<?php

namespace App\Enums;

/**
 * Where an offering's application form and disbursement requirements come
 * from: the admin's standard requirements, or set on the offering by hand.
 */
enum RequirementSource: string
{
    case Standard = 'standard';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard requirements',
            self::Manual => 'Set manually',
        };
    }
}
