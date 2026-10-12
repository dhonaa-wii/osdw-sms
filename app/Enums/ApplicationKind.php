<?php

namespace App\Enums;

/**
 * A new application goes through the form, screening, shortlist and
 * interview. A renewal is generated for an existing scholar in a renewal
 * offering and goes straight to review of its renewal requirements.
 */
enum ApplicationKind: string
{
    case New = 'new';
    case Renewal = 'renewal';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Renewal => 'Renewal',
        };
    }
}
