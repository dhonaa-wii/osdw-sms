<?php

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\AccountService;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reset a forgotten password: email, then the code mailed to it, then a new
 * password. The page never says whether an account uses the email, so it
 * cannot be used to find out who is registered.
 */
new #[Layout('layouts::public', array('title' => 'Reset password'))] class extends Component
{
    #[Locked]
    public string $step = 'email';

    public string $email = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function sendCode(OtpService $otp): void
    {
        $this->email = $otp->normalize($this->email);

        $this->validate(['email' => ['required', 'email', 'max:150']]);

        if ($this->sendIfRegistered($otp, 'email')) {
            $this->step = 'code';
            $this->code = '';
        }
    }

    public function resendCode(OtpService $otp): void
    {
        if ($this->sendIfRegistered($otp, 'code')) {
            $this->resetErrorBag();
            $this->dispatch('code-sent', seconds: OtpService::RESEND_SECONDS);
            $this->dispatch('toast', message: 'A new code is on its way.');
        }
    }

    protected function sendIfRegistered(OtpService $otp, string $errorField): bool
    {
        $user = User::where('email', $this->email)->first();

        if (! $user || ! $user->is_active) {
            return true;
        }

        try {
            $otp->send($this->email, OtpPurpose::PasswordReset);
        } catch (\RuntimeException $e) {
            $this->addError($errorField, $e->getMessage());

            return false;
        }

        return true;
    }

    public function verifyCode(OtpService $otp): void
    {
        // Spaces or dashes copied along with the code are not part of it.
        $this->code = preg_replace('/\D/', '', $this->code);

        $this->validate(
            ['code' => ['required', 'digits:6']],
            ['code.required' => 'Enter the 6-digit code from the email.', 'code.digits' => 'The code is 6 digits.'],
        );

        try {
            $otp->verify($this->email, OtpPurpose::PasswordReset, $this->code);
        } catch (\RuntimeException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session(['password_reset_email' => $this->email]);
        $this->step = 'password';
    }

    public function changeEmail(): void
    {
        session()->forget('password_reset_email');
        $this->reset('code', 'password', 'password_confirmation');
        $this->resetErrorBag();
        $this->step = 'email';
    }

    public function resetPassword(AccountService $accounts): void
    {
        $email = session('password_reset_email');

        if ($email === null || $email !== $this->email) {
            $this->changeEmail();

            return;
        }

        $this->validate(
            ['password' => ['required', 'string', 'min:8', 'confirmed']],
            ['password.min' => 'Use at least 8 characters.', 'password.confirmed' => 'The two passwords do not match.'],
        );

        $user = $accounts->resetPassword($email, $this->password);

        session()->forget('password_reset_email');
        Auth::login($user);
        session()->regenerate();

        session()->flash('toast', 'Your password is changed.');
        $this->redirect($user->homeRoute(), navigate: $user->isStudent());
    }

    public function with(OtpService $otp): array
    {
        return [
            'resendIn' => $this->step === 'code' ? $otp->secondsUntilResend($this->email, OtpPurpose::PasswordReset) : 0,
        ];
    }
};
?>

<x-auth.card title="Reset your password">
    @if ($step === 'email')
        <form wire:submit="sendCode" novalidate class="space-y-4">
            <x-ui.input name="email" label="Email address" type="email" wire:model="email" autocomplete="email" autofocus placeholder="name@example.com" hint="The email you log in with. We'll send it a code." />
            <x-ui.button type="submit" loading="sendCode" class="h-11 w-full">Send code</x-ui.button>
        </form>
    @elseif ($step === 'code')
        <x-auth.code-input :email="$email" :resend-in="$resendIn" />
    @else
        <form wire:submit="resetPassword" novalidate class="space-y-4" x-data="{ reveal: false }">
            <x-ui.input name="password" label="New password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password" autocomplete="new-password" autofocus hint="At least 8 characters." />
            <x-ui.input name="password_confirmation" label="Confirm new password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" />
            <label class="flex cursor-pointer items-center gap-2 font-body text-[13px] text-slate">
                <input type="checkbox" x-model="reveal" class="size-4 accent-navy-600"> Show passwords
            </label>
            <x-ui.button type="submit" loading="resetPassword" class="h-11 w-full">Save new password</x-ui.button>
        </form>
    @endif

    <x-slot:footer>
        <p>Remembered it? <a href="{{ route('login') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Log in</a></p>
    </x-slot:footer>
</x-auth.card>
