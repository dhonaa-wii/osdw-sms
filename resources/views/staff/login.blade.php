<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Log in'))] class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('dashboard');
        }
    }

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $this->addError('email', 'Your account has been deactivated. Contact the system administrator.');

            return;
        }

        session()->regenerate();

        $this->redirectRoute('dashboard');
    }
};
?>

<div class="mx-auto max-w-sm">
    <h1 class="font-display text-[30px] font-600 text-ink">Staff sign in</h1>

    <form wire:submit="login" class="mt-8 space-y-5">
        <x-ui.input name="email" label="Email address" type="email" wire:model="email" autocomplete="username" required />

        <x-ui.input name="password" label="Password" type="password" wire:model="password" autocomplete="current-password" required />

        <label class="flex items-center gap-2 font-body text-[14px] text-slate">
            <input type="checkbox" wire:model="remember" class="rounded-control border-line focus-visible:ring-2 focus-visible:ring-forest-600">
            Keep me signed in
        </label>

        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="login" class="w-full">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in&hellip;</span>
        </x-ui.button>
    </form>
</div>
