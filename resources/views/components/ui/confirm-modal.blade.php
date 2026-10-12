{{--
    One confirmation modal per layout, in place of the browser's confirm().
    Ask from any Alpine expression; the action runs while the button spins,
    then the modal closes:

        x-on:click="$store.confirm.ask({
            title: 'Remove this requirement?',
            message: 'Files already submitted for it will be deleted.',
            confirmLabel: 'Remove requirement',
            action: () => $wire.removeRequirement(4),
        })"

    tone is 'danger' (default) or 'primary'.
--}}
<script data-navigate-once>
    document.addEventListener('alpine:init', () => {
        Alpine.store('confirm', {
            open: false,
            busy: false,
            title: '',
            message: '',
            confirmLabel: 'Confirm',
            tone: 'danger',
            action: null,
            ask(options) {
                Object.assign(this, { title: '', message: '', confirmLabel: 'Confirm', tone: 'danger', action: null }, options, { open: true, busy: false });
            },
            async run() {
                this.busy = true;
                try {
                    if (this.action) await this.action();
                } finally {
                    this.busy = false;
                    this.open = false;
                }
            },
            cancel() {
                if (! this.busy) this.open = false;
            },
        });
    });
</script>

<div x-data x-on:keydown.escape.window="$store.confirm.open && $store.confirm.cancel()">
    <div
        x-show="$store.confirm.open"
        x-cloak
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/40 px-4 py-8"
        data-modal-backdrop
        x-on:click.self="$store.confirm.cancel()"
    >
        <div
            x-show="$store.confirm.open"
            x-trap.noscroll="$store.confirm.open"
            class="w-full max-w-[340px] rounded-card border border-line bg-surface p-5 text-center shadow-xl shadow-ink/10"
            data-modal-panel
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="confirm-modal-title"
            aria-describedby="confirm-modal-message"
        >
            <span
                class="mx-auto flex size-10 items-center justify-center rounded-full"
                :class="$store.confirm.tone === 'danger' ? 'bg-crimson-600/10 text-crimson-600' : 'bg-navy-50 text-navy-600'"
            >
                <x-ui.icon name="alert-circle" class="size-5" />
            </span>
            <h2 id="confirm-modal-title" class="mt-3 font-display text-[15px] font-600 text-ink" x-text="$store.confirm.title"></h2>
            <p id="confirm-modal-message" class="mt-1 font-body text-[13px] leading-relaxed text-slate" x-show="$store.confirm.message" x-text="$store.confirm.message"></p>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    x-on:click="$store.confirm.cancel()"
                    :disabled="$store.confirm.busy"
                    class="inline-flex items-center justify-center rounded-control border border-line bg-surface px-3 py-1.5 font-body text-[13px] font-500 text-ink transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2 disabled:opacity-60"
                >Cancel</button>
                <button
                    type="button"
                    x-on:click="$store.confirm.run()"
                    :disabled="$store.confirm.busy"
                    class="relative inline-flex items-center justify-center rounded-control px-3 py-1.5 font-body text-[13px] font-500 text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
                    :class="$store.confirm.tone === 'danger' ? 'bg-crimson-600 hover:bg-crimson-700 focus-visible:ring-crimson-600' : 'bg-navy-600 hover:bg-navy-700 focus-visible:ring-navy-600'"
                >
                    <span :class="$store.confirm.busy && 'invisible'" x-text="$store.confirm.confirmLabel"></span>
                    <span x-show="$store.confirm.busy" x-cloak class="absolute inset-0 flex items-center justify-center">
                        <x-ui.spinner />
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
