<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Register = 'register';
    case PasswordReset = 'password_reset';

    /**
     * The code leads the subject so it shows in the inbox list, and so
     * Gmail does not stack several code emails into one conversation.
     */
    public function subject(string $code): string
    {
        return match ($this) {
            self::Register => "{$code} is your OSDW Scholarships verification code",
            self::PasswordReset => "{$code} is your OSDW Scholarships password reset code",
        };
    }
}
