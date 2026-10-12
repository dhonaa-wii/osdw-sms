<?php

use App\Enums\OtpPurpose;
use App\Services\AccountService;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create a student account: email, then the code mailed to it, then a
 * password. The verified email is kept in the session, not in a component
 * property, so it cannot be swapped from the browser between steps.
 */
new #[Layout('layouts::public', array('title' => 'Register'))] class extends Component
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

        $this->validate(
            ['email' => ['required', 'email', 'max:150', 'unique:users,email']],
            ['email.unique' => 'An account already uses this email. Log in instead.'],
        );

        try {
            $otp->send($this->email, OtpPurpose::Register);
        } catch (\RuntimeException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        $this->step = 'code';
        $this->code = '';
    }

    public function resendCode(OtpService $otp): void
    {
        try {
            $otp->send($this->email, OtpPurpose::Register);
        } catch (\RuntimeException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->dispatch('code-sent', seconds: OtpService::RESEND_SECONDS);
        $this->dispatch('toast', message: 'A new code is on its way.');
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
            $otp->verify($this->email, OtpPurpose::Register, $this->code);
        } catch (\RuntimeException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session(['registration_email' => $this->email]);
        $this->step = 'password';
    }

    public function changeEmail(): void
    {
        session()->forget('registration_email');
        $this->reset('code', 'password', 'password_confirmation');
        $this->resetErrorBag();
        $this->step = 'email';
    }

    public function createAccount(AccountService $accounts): void
    {
        $email = session('registration_email');

        if ($email === null || $email !== $this->email) {
            $this->changeEmail();

            return;
        }

        $this->validate(
            [
                'email' => ['unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'email.unique' => 'An account already uses this email. Log in instead.',
                'password.min' => 'Use at least 8 characters.',
                'password.confirmed' => 'The two passwords do not match.',
            ],
        );

        $user = $accounts->registerStudent($email, $this->password);

        session()->forget('registration_email');
        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('student.profile.complete', navigate: true);
    }

    public function with(OtpService $otp): array
    {
        return [
            'resendIn' => $this->step === 'code' ? $otp->secondsUntilResend($this->email, OtpPurpose::Register) : 0,
        ];
    }
};
?>

<x-auth.card title="Register">
    @if ($step === 'email')
        <x-auth.google-button />

        <form wire:submit="sendCode" novalidate class="space-y-4">
            <x-ui.input name="email" label="Email address" type="email" wire:model="email" autocomplete="email" autofocus placeholder="name@example.com" hint="We'll send a code to confirm it's yours." />
            <x-ui.button type="submit" loading="sendCode" class="h-11 w-full">Send code</x-ui.button>
        </form>
    @elseif ($step === 'code')
        <x-auth.code-input :email="$email" :resend-in="$resendIn" />
    @else
        <form wire:submit="createAccount" novalidate class="space-y-4" x-data="{ reveal: false }">
            <p class="flex items-center gap-2 font-body text-[14px] text-ink">
                <x-ui.icon name="check-circle" class="size-4 text-navy-600" />
                <span class="break-all">{{ $email }}</span>
            </p>
            <x-ui.input name="password" label="Password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password" autocomplete="new-password" autofocus hint="At least 8 characters. Not your email password." />
            <x-ui.input name="password_confirmation" label="Confirm password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" />
            <label class="flex cursor-pointer items-center gap-2 font-body text-[13px] text-slate">
                <input type="checkbox" x-model="reveal" class="size-4 accent-navy-600"> Show passwords
            </label>
            <x-ui.button type="submit" loading="createAccount" class="h-11 w-full">Register</x-ui.button>
        </form>
    @endif

    <x-slot:footer>
        <p>Already have an account? <a href="{{ route('login') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Log in</a></p>
    </x-slot:footer>
</x-auth.card>
