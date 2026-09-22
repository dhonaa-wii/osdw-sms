<?php

namespace App\Enums;

enum EmailType: string
{
    case Reference = 'reference';
    case ScreeningResult = 'screening_result';
    case AwardNotice = 'award_notice';
    case Reminder = 'reminder';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }
}
