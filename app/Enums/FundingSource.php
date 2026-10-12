<?php

namespace App\Enums;

enum FundingSource: string
{
    case NationalGovernment = 'national_government';
    case NationalNonGovernment = 'national_non_government';
    case LocalGovernment = 'local_government';
    case LocalNonGovernment = 'local_non_government';
    case InHouse = 'in_house';
    case Academic = 'academic';
    case NonAcademic = 'non_academic';

    public function label(): string
    {
        return match ($this) {
            self::NationalGovernment => 'National Government',
            self::NationalNonGovernment => 'National Non-Government',
            self::LocalGovernment => 'Local Government',
            self::LocalNonGovernment => 'Local Non-Government',
            self::InHouse => 'In-house',
            self::Academic => 'Academic',
            self::NonAcademic => 'Non-academic',
        };
    }

    /**
     * National scholarships (government or not) are one per student: a
     * grantee of one can't apply to or be awarded another.
     */
    public function isNational(): bool
    {
        return in_array($this, [self::NationalGovernment, self::NationalNonGovernment], true);
    }
}
