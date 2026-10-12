<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Mail\OtpMail;
use App\Models\EmailOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Six-digit codes mailed to prove a person owns an email address — before
 * an account is created on it, and before its password is reset.
 */
class OtpService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public function send(string $email, OtpPurpose $purpose): void
    {
        $email = $this->normalize($email);

        if ($this->secondsUntilResend($email, $purpose) > 0) {
            throw new \RuntimeException('Wait a minute before asking for a new code.');
        }

        // Expired codes are no use to anyone. Unexpired ones stay valid, so a
        // student reading an earlier email is not caught out by a resend.
        EmailOtp::where('email', $email)->where('purpose', $purpose->value)->where('expires_at', '<', now())->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = EmailOtp::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        try {
            Mail::to($email)->send(new OtpMail($code, $purpose, self::TTL_MINUTES));
        } catch (Throwable $e) {
            $otp->delete();
            report($e);

            throw new \RuntimeException("We couldn't send the email. Check the address and try again in a few minutes.");
        }
    }

    public function secondsUntilResend(string $email, OtpPurpose $purpose): int
    {
        $latest = EmailOtp::where('email', $this->normalize($email))
            ->where('purpose', $purpose->value)
            ->latest('id')
            ->first();

        if (! $latest) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) $latest->created_at->diffInSeconds(now()));
    }

    /**
     * Accepts any unused code for this email that has not expired — the
     * student may be reading an earlier email than the latest one. Wrong
     * tries count against the newest code; once it hits the limit, every
     * code for the email stops working until a new one is sent. A match
     * uses up all of them.
     */
    public function verify(string $email, OtpPurpose $purpose, string $code): void
    {
        $live = EmailOtp::where('email', $this->normalize($email))
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->get();

        if ($live->isEmpty()) {
            throw new \RuntimeException('That code has expired. Send a new one.');
        }

        $newest = $live->first();

        if ($newest->attempts >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Too many wrong tries. Send a new code.');
        }

        $matched = $live->contains(fn (EmailOtp $otp) => Hash::check(trim($code), $otp->code_hash));

        if (! $matched) {
            $newest->increment('attempts');

            throw new \RuntimeException('That code is not right. Use the code from the newest email and try again.');
        }

        EmailOtp::whereIn('id', $live->pluck('id'))->update(['consumed_at' => now()]);
    }

    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
