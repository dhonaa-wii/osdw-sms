<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Screening = 'screening';
    case Shortlisted = 'shortlisted';
    case Approved = 'approved';
    case Waitlisted = 'waitlisted';
    case Denied = 'denied';
    case Accepted = 'accepted';
    case Forfeited = 'forfeited';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Denied, self::Forfeited, self::Withdrawn => 'border-crimson-600 text-crimson-600',
            self::Submitted, self::Screening, self::Waitlisted => 'border-amber-600 text-amber-600',
            self::Shortlisted, self::Approved, self::Accepted => 'border-forest-600 text-forest-700',
            default => 'border-mist text-mist',
        };
    }
}
