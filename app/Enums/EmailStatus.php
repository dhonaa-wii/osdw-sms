<?php

namespace App\Enums;

enum EmailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->title();
    }

    public function stampClasses(): string
    {
        return match ($this) {
            self::Failed => 'border-crimson-600 text-crimson-600',
            self::Queued => 'border-amber-600 text-amber-600',
            self::Sent => 'border-navy-600 text-navy-700',
        };
    }
}
