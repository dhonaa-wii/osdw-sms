<?php

namespace App\Enums;

enum DegreeLevel: string
{
    case Undergraduate = 'undergraduate';
    case Graduate = 'graduate';

    public function label(): string
    {
        return str($this->value)->title();
    }
}
