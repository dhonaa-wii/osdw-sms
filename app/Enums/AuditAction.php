<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Approved = 'approved';
    case Overridden = 'overridden';

    public function label(): string
    {
        return str($this->value)->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Deleted => 'border-crimson-600 text-crimson-600',
            self::Overridden => 'border-amber-600 text-amber-600',
            self::Updated => 'border-azure-600 text-azure-600',
            self::Created, self::Approved => 'border-navy-600 text-navy-700',
        };
    }
}
