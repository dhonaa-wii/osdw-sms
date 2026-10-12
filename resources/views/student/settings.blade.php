<?php

use App\Livewire\Concerns\EditsStudentProfile;
use App\Services\AccountService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Settings', 'noindex' => true))] class extends Component
{
    use EditsStudentProfile;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->fillProfile(auth()->user()->applicant);
    }

    public function saveProfile(AccountService $accounts): void
    {
        $this->validate($this->profileRules());

        $accounts->updateProfile(auth()->user(), $this->profileData());

        $this->dispatch('toast', message: 'Profile saved.');
    }

    public function savePassword(AccountService $accounts): void
    {
        $user = auth()->user();

        // An account made with Google has no password yet; it sets one without the old one.
        $this->validate(
            [
                'current_password' => $user->password ? ['required', 'string'] : ['nullable'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'current_password.required' => 'Enter your current password.',
                'password.min' => 'Use at least 8 characters.',
                'password.confirmed' => 'The two passwords do not match.',
            ],
        );

        if ($user->password && ! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'That is not your current password.');

            return;
        }

        $accounts->setPassword($user, $this->password);
        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', message: 'Password saved.');
    }
};
?>

@php
    $user = auth()->user();
    $applicant = $user->applicant;
    $cardHeading = 'flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink';
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <h1 class="font-display text-[24px] font-600 text-ink">Settings</h1>

    <section class="rounded-card border border-line bg-surface" aria-labelledby="profile-heading">
        <h2 id="profile-heading" class="{{ $cardHeading }}"><x-ui.icon name="user" class="size-4 text-navy-600" /> Profile</h2>
        <form wire:submit="saveProfile" novalidate class="space-y-5 p-5">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="font-body text-[13px] font-500 text-ink">Student ID</dt>
                    <dd class="mt-1 font-mono text-[14px] text-slate">{{ $applicant->student_id }}</dd>
                </div>
                <div>
                    <dt class="font-body text-[13px] font-500 text-ink">Email</dt>
                    <dd class="mt-1 break-all font-body text-[14px] text-slate">{{ $user->email }}</dd>
                </div>
            </dl>

            <x-student.profile-fields class="border-t border-line pt-5" />

            <div class="flex justify-end border-t border-line pt-4">
                <x-ui.button type="submit" loading="saveProfile">Save profile</x-ui.button>
            </div>
        </form>
    </section>

    <section class="rounded-card border border-line bg-surface" aria-labelledby="password-heading">
        <h2 id="password-heading" class="{{ $cardHeading }}"><x-ui.icon name="lock" class="size-4 text-navy-600" /> {{ $user->password ? 'Change password' : 'Set a password' }}</h2>
        <form wire:submit="savePassword" novalidate class="space-y-4 p-5" x-data="{ reveal: false }">
            @if ($user->password)
                <div class="sm:w-1/2 sm:pr-2">
                    <x-ui.input name="current_password" label="Current password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="current_password" autocomplete="current-password" />
                </div>
            @else
                <p class="font-body text-[13px] text-slate">You sign in with Google. Set a password to also log in with your email.</p>
            @endif
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.input name="password" label="New password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password" autocomplete="new-password" hint="At least 8 characters." />
                <x-ui.input name="password_confirmation" label="Confirm new password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                <label class="flex cursor-pointer items-center gap-2 font-body text-[13px] text-slate">
                    <input type="checkbox" x-model="reveal" class="size-4 accent-navy-600"> Show passwords
                </label>
                <x-ui.button type="submit" loading="savePassword">Save password</x-ui.button>
            </div>
        </form>
    </section>
</div>
