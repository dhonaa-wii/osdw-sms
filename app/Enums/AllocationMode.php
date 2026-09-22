<?php

namespace App\Enums;

enum AllocationMode: string
{
    case Open = 'open';
    case Quota = 'quota';
    case Proportional = 'proportional';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
