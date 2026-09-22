<?php

namespace App\Enums;

enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
