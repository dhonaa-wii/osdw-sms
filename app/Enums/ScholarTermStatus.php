<?php

namespace App\Enums;

enum ScholarTermStatus: string
{
    case Active = 'active';
    case Probation = 'probation';
    case Terminated = 'terminated';
    case Graduated = 'graduated';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
