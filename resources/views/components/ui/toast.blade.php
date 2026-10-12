{{--
    One toast stack per layout. A component calls $this->dispatch('toast', message: '…')
    and the message floats in the corner instead of pushing the page content down.
--}}
<div
    x-data="{
        toasts: [],
        push(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: detail.message ?? '', tone: detail.tone ?? 'success' });
            setTimeout(() => this.dismiss(id), 4000);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    @toast.window="push($event.detail)"
    {{-- A redirect can carry one toast to the next page with session()->flash('toast', '…'). --}}
    @if (session('toast')) x-init="push({ message: @js(session('toast')) })" @endif
    class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end"
    role="status"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-card border border-line bg-surface py-2.5 pl-3 pr-2 shadow-lg"
        >
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full" :class="toast.tone === 'error' ? 'bg-crimson-600 text-white' : 'bg-navy-600 text-white'">
                <x-ui.icon name="check" class="size-4" x-show="toast.tone !== 'error'" />
                <x-ui.icon name="alert-circle" class="size-4" x-show="toast.tone === 'error'" />
            </span>
            <p class="min-w-0 flex-1 font-body text-[14px] text-ink" x-text="toast.message"></p>
            <button type="button" @click="dismiss(toast.id)" class="flex size-7 shrink-0 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Dismiss">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
