<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Screening = 'screening';
    case Shortlisted = 'shortlisted';
    case Awarded = 'awarded';
    case Waitlisted = 'waitlisted';
    case Denied = 'denied';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Denied => 'border-crimson-600 text-crimson-600',
            self::Submitted, self::Screening, self::Waitlisted => 'border-amber-600 text-amber-600',
            self::Shortlisted, self::Awarded => 'border-navy-600 text-navy-700',
            default => 'border-mist text-mist',
        };
    }
}
