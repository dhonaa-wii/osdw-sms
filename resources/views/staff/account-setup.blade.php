<?php

use App\Services\AccountService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A staff account's first sign-in. The administrator gave them a generated
 * password; they replace it and connect a Google account before they can
 * open any other page (the staff.setup middleware sends them here).
 */
new #[Layout('layouts::public', array('title' => 'Set up your account', 'noindex' => true))] class extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public ?string $googleError = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->needsAccountSetup()) {
            $this->redirect($user->homeRoute());

            return;
        }

        // The Google callback reports back through a flashed notice.
        $notice = session('notice');

        if (($notice['tone'] ?? null) === 'error') {
            $this->googleError = $notice['message'];
        } elseif ($notice) {
            $this->dispatch('toast', message: $notice['title'].'.');
        }
    }

    public function savePassword(AccountService $accounts): void
    {
        $this->validate(
            ['password' => ['required', 'string', 'min:8', 'confirmed']],
            [
                'password.required' => 'Enter a new password.',
                'password.min' => 'Use at least 8 characters.',
                'password.confirmed' => 'The two passwords do not match.',
            ],
        );

        $user = auth()->user();

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Choose a password different from the temporary one.');

            return;
        }

        $accounts->setPassword($user, $this->password);
        $this->reset('password', 'password_confirmation');

        if (! $user->fresh()->needsAccountSetup()) {
            session()->flash('notice', ['title' => 'Your account is set up']);
            $this->redirect(route('dashboard'));

            return;
        }

        $this->dispatch('toast', message: 'Password saved.');
    }
};
?>

@php
    $user = auth()->user();
    $passwordDone = $user->password_changed_at !== null;
    $googleDone = $user->google_id !== null;
    $googleOn = filled(config('services.google.client_id'));
    $step = 'flex size-6 shrink-0 items-center justify-center rounded-full font-display text-[12px] font-600';
@endphp

<x-auth.card title="Set up your account" width="max-w-md">
    <ol class="space-y-5">
        <li>
            <div class="flex items-center gap-2.5">
                @if ($passwordDone)
                    <span class="{{ $step }} bg-navy-600 text-white"><x-ui.icon name="check" class="size-3.5" /></span>
                @else
                    <span class="{{ $step }} bg-navy-50 text-navy-700">1</span>
                @endif
                <h2 class="font-display text-[15px] font-600 text-ink">Set your own password</h2>
            </div>

            @if ($passwordDone)
                <p class="mt-1.5 pl-[34px] font-body text-[13px] text-slate">Password saved.</p>
            @else
                <form wire:submit="savePassword" novalidate class="mt-3 space-y-4 pl-[34px]" x-data="{ reveal: false }">
                    <x-ui.input name="password" label="New password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password" autocomplete="new-password" hint="At least 8 characters." />
                    <x-ui.input name="password_confirmation" label="Confirm new password" type="password" x-bind:type="reveal ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" />
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="flex cursor-pointer items-center gap-2 font-body text-[13px] text-slate">
                            <input type="checkbox" x-model="reveal" class="size-4 accent-navy-600"> Show passwords
                        </label>
                        <x-ui.button type="submit" loading="savePassword">Save password</x-ui.button>
                    </div>
                </form>
            @endif
        </li>

        @if ($googleOn)
            <li class="border-t border-line pt-5">
                <div class="flex items-center gap-2.5">
                    @if ($googleDone)
                        <span class="{{ $step }} bg-navy-600 text-white"><x-ui.icon name="check" class="size-3.5" /></span>
                    @else
                        <span class="{{ $step }} bg-navy-50 text-navy-700">2</span>
                    @endif
                    <h2 class="font-display text-[15px] font-600 text-ink">Connect your Google account</h2>
                </div>

                <div class="mt-1.5 pl-[34px]">
                    @if ($googleDone)
                        <p class="font-body text-[13px] text-slate">Connected. You can log in with Google.</p>
                    @else
                        <p class="font-body text-[13px] text-slate">You will be able to log in with Google.</p>
                        @if ($googleError)
                            <p class="mt-2 font-body text-[13px] text-crimson-600" role="alert">{{ $googleError }}</p>
                        @endif
                        <x-staff.google-connect-button class="mt-3" />
                    @endif
                </div>
            </li>
        @endif
    </ol>

    <x-slot:footer>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-control font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Log out</button>
        </form>
    </x-slot:footer>
</x-auth.card>
