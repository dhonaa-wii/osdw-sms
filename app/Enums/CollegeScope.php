<?php

namespace App\Enums;

enum CollegeScope: string
{
    case All = 'all';
    case Restricted = 'restricted';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
