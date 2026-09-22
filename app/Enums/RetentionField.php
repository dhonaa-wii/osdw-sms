<?php

namespace App\Enums;

enum RetentionField: string
{
    case Gwa = 'gwa';
    case UnitsEnrolled = 'units_enrolled';
    case FailingGrade = 'failing_grade';

    public function label(): string
    {
        return match ($this) {
            self::Gwa => 'GWA',
            self::UnitsEnrolled => 'Units Enrolled',
            self::FailingGrade => 'Failing Grades',
        };
    }
}
