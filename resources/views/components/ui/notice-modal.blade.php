{{--
    One notice modal per layout. A component calls
    $this->dispatch('notice', title: '…', message: '…', tone: 'success'|'error')
    and the message opens in a small modal instead of an alert pushed into the page.
    A redirect can carry one to the next page with session()->flash('notice', ['title' => '…']).
--}}
<div
    @if (session('notice')) x-init="$nextTick(() => show(@js(session('notice'))))" @endif
    x-data="{
        open: false,
        title: '',
        message: '',
        tone: 'success',
        show(detail) {
            this.title = detail.title ?? '';
            this.message = detail.message ?? '';
            this.tone = detail.tone ?? 'success';
            this.open = true;
        },
    }"
    @notice.window="show($event.detail)"
    @keydown.escape.window="open = false"
>
    <div
        x-show="open"
        x-cloak
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/40 px-4 py-8"
        data-modal-backdrop
        @click.self="open = false"
    >
        <div
            x-show="open"
            x-trap.noscroll="open"
            class="w-full max-w-[340px] rounded-card border border-line bg-surface p-5 text-center shadow-xl shadow-ink/10"
            data-modal-panel
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="notice-modal-title"
            aria-describedby="notice-modal-message"
        >
            <span
                class="mx-auto flex size-10 items-center justify-center rounded-full"
                :class="tone === 'error' ? 'bg-crimson-600/10 text-crimson-600' : 'bg-navy-50 text-navy-600'"
            >
                <x-ui.icon name="check-circle" class="size-5" x-show="tone !== 'error'" />
                <x-ui.icon name="alert-circle" class="size-5" x-show="tone === 'error'" />
            </span>
            <h2 id="notice-modal-title" class="mt-3 font-display text-[15px] font-600 text-ink" x-text="title"></h2>
            <p id="notice-modal-message" class="mt-1 font-body text-[13px] leading-relaxed text-slate" x-show="message" x-text="message"></p>
            <x-ui.button class="mt-5 w-full py-1.5 text-[13px]" x-on:click="open = false">Done</x-ui.button>
        </div>
    </div>
</div>
