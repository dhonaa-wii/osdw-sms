<?php

use App\Services\AccountService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * A staff member's or administrator's own account: their name, their
 * password and the Google account they log in with.
 */
new #[Layout('layouts::staff', array('title' => 'Profile'))] class extends Component
{
    #[Validate('required|string|max:150', message: ['required' => 'Enter your name.'])]
    public string $name = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
    }

    public function saveName(AccountService $accounts): void
    {
        $this->validateOnly('name');

        $accounts->updateStaffName(auth()->user(), $this->name);

        $this->dispatch('notice', title: 'Name saved');
    }

    public function savePassword(AccountService $accounts): void
    {
        $user = auth()->user();

        $this->validate(
            [
                'current_password' => $user->password ? ['required', 'string'] : ['nullable'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'current_password.required' => 'Enter your current password.',
                'password.required' => 'Enter a new password.',
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

        $this->dispatch('notice', title: 'Password saved');
    }
};
?>

@php
    $user = auth()->user();
    $googleOn = filled(config('services.google.client_id'));
    $cardHeading = 'flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink';
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <h1 class="font-display text-[26px] font-600 text-ink">Profile</h1>

    <section class="rounded-card border border-line bg-surface" aria-labelledby="account-heading">
        <h2 id="account-heading" class="{{ $cardHeading }}"><x-ui.icon name="user" class="size-4 text-navy-600" /> Account</h2>
        <form wire:submit="saveName" novalidate class="space-y-5 p-5">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="font-body text-[13px] font-500 text-ink">Email</dt>
                    <dd class="mt-1 break-all font-body text-[14px] text-slate">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="font-body text-[13px] font-500 text-ink">Role</dt>
                    <dd class="mt-1 font-body text-[14px] text-slate">{{ $user->role->label() }}</dd>
                </div>
            </dl>

            <div class="border-t border-line pt-5 sm:w-1/2 sm:pr-2">
                <x-ui.input name="name" label="Name" wire:model="name" required maxlength="150" autocomplete="name" />
            </div>

            <div class="flex justify-end border-t border-line pt-4">
                <x-ui.button type="submit" loading="saveName">Save name</x-ui.button>
            </div>
        </form>
    </section>

    <section class="rounded-card border border-line bg-surface" aria-labelledby="password-heading">
        <h2 id="password-heading" class="{{ $cardHeading }}"><x-ui.icon name="lock" class="size-4 text-navy-600" /> Change password</h2>
        <form wire:submit="savePassword" novalidate class="space-y-4 p-5" x-data="{ reveal: false }">
            @if ($user->password)
                <div class="sm:w-1/2 sm:pr-2">
                    <x-ui.input name="current_password" label="Current password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="current_password" autocomplete="current-password" />
                </div>
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

    @if ($googleOn)
        <section class="rounded-card border border-line bg-surface" aria-labelledby="google-heading">
            <h2 id="google-heading" class="{{ $cardHeading }}"><x-ui.icon name="link" class="size-4 text-navy-600" /> Google account</h2>
            <div class="flex flex-wrap items-center justify-between gap-3 p-5">
                @if ($user->google_id)
                    <p class="font-body text-[14px] text-slate">Connected. You can log in with Google.</p>
                    <x-staff.google-connect-button label="Use a different Google account" />
                @else
                    <p class="font-body text-[14px] text-slate">Connect one to log in with Google.</p>
                    <x-staff.google-connect-button />
                @endif
            </div>
        </section>
    @endif
</div>
