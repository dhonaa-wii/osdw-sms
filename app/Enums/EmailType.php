<?php

namespace App\Enums;

enum EmailType: string
{
    case ScreeningResult = 'screening_result';
    case AwardNotice = 'award_notice';
    case RenewalNotice = 'renewal_notice';
    case Reminder = 'reminder';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }
}
