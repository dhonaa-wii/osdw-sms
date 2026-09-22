<?php

namespace App\Enums;

enum CheckType: string
{
    case Completeness = 'completeness';
    case Eligibility = 'eligibility';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
