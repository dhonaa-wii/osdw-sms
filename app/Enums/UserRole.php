<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case OsdwStaff = 'osdw_staff';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'System Administrator',
            self::OsdwStaff => 'OSDW Staff',
            self::Student => 'Student',
        };
    }

    /**
     * The roles that sign in to the staff side (dashboard, review, admin).
     *
     * @return array<int, self>
     */
    public static function staffRoles(): array
    {
        return [self::Admin, self::OsdwStaff];
    }

    public function isStaff(): bool
    {
        return $this !== self::Student;
    }
}
