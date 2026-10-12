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
        // Google sign-in sends its errors back here through the session.
        if (session('errors')?->has('email')) {
            $this->addError('email', session('errors')->first('email'));
        }
    }

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => mb_strtolower(trim($this->email)), 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $this->addError('email', 'Your account has been deactivated. Contact the OSDW office.');

            return;
        }

        session()->regenerate();

        // Back to the page that asked them to sign in (e.g. Apply), or their home.
        $this->redirectIntended(default: $user->homeRoute(), navigate: $user->isStudent());
    }
};
?>

@php
    $fieldClass = 'block h-11 w-full rounded-control border bg-surface pl-10 font-body text-[15px] text-ink placeholder:text-mist transition-colors focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25';
@endphp

<x-auth.card title="Log in">
    <x-auth.google-button />

    <form wire:submit="login" novalidate class="space-y-4">
        <div>
            <label for="email" class="block font-body text-[13px] font-500 text-ink">Email address</label>
            <div class="relative mt-1">
                <x-ui.icon name="mail" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="username"
                    autofocus
                    placeholder="name@example.com"
                    @error('email') aria-invalid="true" @enderror
                    class="{{ $fieldClass }} pr-3 {{ $errors->has('email') ? 'border-crimson-600' : 'border-line' }}"
                >
            </div>
            @error('email')
                <p class="mt-1 flex items-start gap-1.5 font-body text-[13px] text-crimson-600" role="alert">
                    <x-ui.icon name="alert-circle" class="mt-0.5 size-4" />
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        <div x-data="{ reveal: false }">
            <div class="flex items-baseline justify-between gap-2">
                <label for="password" class="block font-body text-[13px] font-500 text-ink">Password</label>
                <a href="{{ route('password.request') }}" wire:navigate class="font-body text-[12px] font-500 text-navy-700 hover:underline">Forgot password?</a>
            </div>
            <div class="relative mt-1">
                <x-ui.icon name="lock" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                <input
                    id="password"
                    :type="reveal ? 'text' : 'password'"
                    type="password"
                    wire:model="password"
                    autocomplete="current-password"
                    @error('password') aria-invalid="true" @enderror
                    class="{{ $fieldClass }} pr-11 {{ $errors->has('password') ? 'border-crimson-600' : 'border-line' }}"
                >
                <button
                    type="button"
                    @click="reveal = ! reveal"
                    :aria-label="reveal ? 'Hide password' : 'Show password'"
                    :class="reveal ? 'text-navy-700' : 'text-mist'"
                    class="absolute right-1.5 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-control transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                >
                    <x-ui.icon name="eye" class="size-4" />
                </button>
            </div>
            @error('password')
                <p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2 font-body text-[14px] text-slate">
            <input type="checkbox" wire:model="remember" class="size-4 accent-navy-600">
            Keep me signed in
        </label>

        <x-ui.button type="submit" loading="login" class="h-11 w-full">Log in</x-ui.button>
    </form>

    <x-slot:footer>
        <p>New here? <a href="{{ route('register') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Register</a></p>
        <p><a href="{{ route('programs.index') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Browse scholarships</a></p>
    </x-slot:footer>
</x-auth.card>
