<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case OsdwStaff = 'osdw_staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'System Administrator',
            self::OsdwStaff => 'OSDW Staff',
        };
    }
}
