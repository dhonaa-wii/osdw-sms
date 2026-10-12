{{--
    The six-digit email code step shared by registration and password reset.
    Expects the component to have `code`, `verifyCode()`, `resendCode()`,
    `changeEmail()` and to pass the email and seconds left before a resend.
--}}
@props(['email', 'resendIn' => 0])

<form wire:submit="verifyCode" novalidate class="space-y-4">
    <p class="font-body text-[14px] text-slate">
        Enter the 6-digit code we sent to <span class="break-all font-500 text-ink">{{ $email }}</span>.
    </p>

    <div>
        <label for="code" class="block font-body text-[13px] font-500 text-ink">Verification code</label>
        <input
            id="code"
            type="text"
            wire:model="code"
            inputmode="numeric"
            autocomplete="one-time-code"
            {{-- A code copied from an email often carries spaces or line breaks; keep only the digits. --}}
            x-on:paste.prevent="$el.value = ($event.clipboardData.getData('text').match(/\d/g) || []).join('').slice(0, 6); $el.dispatchEvent(new Event('input'))"
            x-on:input="if (/\D/.test($el.value) || $el.value.length > 6) { $el.value = $el.value.replace(/\D/g, '').slice(0, 6); $el.dispatchEvent(new Event('input')) }"
            autofocus
            placeholder="000000"
            @error('code') aria-invalid="true" @enderror
            class="mt-1 block h-12 w-full rounded-control border bg-surface text-center font-mono text-[22px] tracking-[0.4em] text-ink placeholder:text-line focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25 {{ $errors->has('code') ? 'border-crimson-600' : 'border-line' }}"
        >
        @error('code')
            <p class="mt-1 flex items-start gap-1.5 font-body text-[13px] text-crimson-600" role="alert">
                <x-ui.icon name="alert-circle" class="mt-0.5 size-4" />
                <span>{{ $message }}</span>
            </p>
        @else
            <p class="mt-1 font-body text-[12px] text-mist">The code expires in {{ \App\Services\OtpService::TTL_MINUTES }} minutes.</p>
        @enderror
    </div>

    <x-ui.button type="submit" loading="verifyCode" class="h-11 w-full">Verify code</x-ui.button>

    <div
        class="flex items-center justify-between gap-2 font-body text-[13px]"
        x-data="{ left: {{ (int) $resendIn }}, timer: null }"
        x-init="timer = setInterval(() => left > 0 && left--, 1000)"
        x-on:code-sent.window="left = $event.detail.seconds"
        x-on:remove="clearInterval(timer)"
    >
        <button type="button" wire:click="changeEmail" class="rounded-control font-500 text-slate hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Use a different email</button>
        <button
            type="button"
            wire:click="resendCode"
            wire:loading.attr="disabled"
            wire:target="resendCode"
            x-bind:disabled="left > 0"
            class="rounded-control font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:cursor-not-allowed disabled:text-mist disabled:no-underline"
        >
            <span x-show="left > 0">Send a new code in <span class="font-mono" x-text="left"></span>s</span>
            <span x-show="left === 0">Send a new code</span>
        </button>
    </div>
</form>
