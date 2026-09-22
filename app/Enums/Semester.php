<?php

namespace App\Enums;

enum Semester: string
{
    case First = 'first';
    case Second = 'second';
    case Summer = 'summer';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
