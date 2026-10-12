<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Accounts. A student account is tied to one email; once the student fills
 * in their profile it owns one applicant record, keyed on a student ID no
 * other account can claim. Staff accounts are made by the administrator
 * with a generated password the staff member replaces at first sign-in.
 */
class AccountService
{
    /**
     * Called after the email's OTP is verified.
     */
    public function registerStudent(string $email, string $password): User
    {
        return User::create([
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Student,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Sign in, link, or sign up through Google. Google has already verified
     * the email, so no OTP is needed. An existing account with the same
     * email (student or staff) is linked rather than duplicated.
     */
    public function signInWithGoogle(SocialiteUser $google): User
    {
        $email = mb_strtolower((string) $google->getEmail());

        if ($email === '') {
            throw new \RuntimeException('Your Google account did not share an email address.');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $google->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $google->getId(),
                'password' => null,
                'role' => UserRole::Student,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->is_active) {
            throw new \RuntimeException('Your account has been deactivated. Contact the OSDW office.');
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data  student_id plus the personal fields
     */
    public function completeProfile(User $user, array $data): Applicant
    {
        if ($user->applicant) {
            throw new \RuntimeException('Your profile is already set up.');
        }

        return DB::transaction(function () use ($user, $data) {
            $applicant = Applicant::create([
                ...$this->personalFields($data),
                'user_id' => $user->id,
                'student_id' => trim($data['student_id']),
                'email' => $user->email,
            ]);

            $user->update(['name' => $applicant->first_name.' '.$applicant->last_name]);

            return $applicant;
        });
    }

    /**
     * Everything but the student ID and email, which are fixed once set.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): Applicant
    {
        return DB::transaction(function () use ($user, $data) {
            $applicant = $user->applicant;
            $applicant->update($this->personalFields($data));
            $user->update(['name' => $applicant->first_name.' '.$applicant->last_name]);

            return $applicant;
        });
    }

    /**
     * A password the account holder chose themselves, which also ends a
     * staff member's temporary-password state.
     */
    public function setPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password, 'password_changed_at' => now()])->save();
    }

    /**
     * Called after the email's password-reset OTP is verified.
     */
    public function resetPassword(string $email, string $password): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->setPassword($user, $password);

        return $user;
    }

    /**
     * Staff accounts are made by the administrator. The system generates the
     * first password; the staff member replaces it at their first sign-in.
     *
     * @return array{0: User, 1: string} the account and its temporary password
     */
    public function createStaff(string $name, string $email): array
    {
        $password = $this->temporaryPassword();

        $user = User::create([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'role' => UserRole::OsdwStaff,
            'password' => $password,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, $password];
    }

    /**
     * A staff member or administrator editing their own name. Their email is
     * changed only by the administrator.
     */
    public function updateStaffName(User $user, string $name): void
    {
        $user->update(['name' => trim($name)]);
    }

    /**
     * A new generated password. The staff member must change it again at
     * their next sign-in; their Google link is kept.
     */
    public function resetStaffPassword(User $user): string
    {
        $password = $this->temporaryPassword();

        $user->forceFill(['password' => $password, 'password_changed_at' => null])->save();

        return $password;
    }

    /**
     * Tie a signed-in account to a Google account, so it can sign in with
     * Google from then on. A Google account already used by — or whose email
     * belongs to — another account is refused.
     */
    public function linkGoogle(User $user, SocialiteUser $google): void
    {
        $email = mb_strtolower((string) $google->getEmail());

        $taken = User::whereKeyNot($user->id)
            ->where(fn ($query) => $query->where('google_id', $google->getId())
                ->when($email !== '', fn ($query) => $query->orWhere('email', $email)))
            ->exists();

        if ($taken) {
            throw new \RuntimeException('That Google account is already used by another account. Choose a different one.');
        }

        $user->forceFill(['google_id' => $google->getId()])->save();
    }

    /**
     * Twelve characters without look-alikes (0/O, 1/l/I), since the
     * administrator reads it out or copies it by hand.
     */
    protected function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return collect(range(1, 12))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->join('');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function personalFields(array $data): array
    {
        return [
            'last_name' => trim($data['last_name']),
            'first_name' => trim($data['first_name']),
            'middle_name' => trim((string) ($data['middle_name'] ?? '')) ?: null,
            'suffix' => trim((string) ($data['suffix'] ?? '')) ?: null,
            'sex' => $data['sex'],
            'birthdate' => ($data['birthdate'] ?? '') ?: null,
            'mobile' => trim((string) ($data['mobile'] ?? '')) ?: null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
        ];
    }
}
