<?php

namespace App\Enums;

enum ScholarshipCategory: string
{
    case Academic = 'academic';
    case NonAcademic = 'non_academic';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }
}
