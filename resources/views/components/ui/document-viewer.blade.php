{{--
    One viewer per page, placed in the layout. Any <x-ui.document-link> dispatches
    `open-document` with the file's url, name and mime type; images and PDFs render
    inline, anything else offers a download since browsers can't display it.
--}}
<div
    x-data="{
        open: false,
        url: null,
        name: '',
        title: '',
        kind: 'other',
        show(detail) {
            this.url = detail.url;
            this.name = detail.name ?? '';
            this.title = detail.title || this.name;
            this.kind = this.kindOf(detail.mime ?? '', this.name);
            this.open = true;
        },
        close() {
            this.open = false;
            this.url = null;
        },
        kindOf(mime, name) {
            const extension = name.split('.').pop().toLowerCase();
            if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) return 'image';
            if (mime === 'application/pdf' || extension === 'pdf') return 'pdf';
            return 'other';
        },
    }"
    @open-document.window="show($event.detail)"
    @keydown.escape.window="open && close()"
>
    <template x-if="open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4 py-8" @click.self="close()">
            <div
                class="flex h-full max-h-[92vh] w-full max-w-5xl flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10"
                role="dialog"
                aria-modal="true"
                aria-labelledby="document-viewer-title"
                x-trap.noscroll="open"
            >
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-line py-2 pl-4 pr-2">
                    <div class="min-w-0">
                        <h2 id="document-viewer-title" class="truncate font-display text-[15px] font-600 text-ink" x-text="title"></h2>
                        <p class="truncate font-body text-[12px] text-slate" x-show="title !== name" x-text="name"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a :href="url" :download="name" class="inline-flex items-center gap-1.5 rounded-control border border-line px-3 py-1.5 font-body text-[13px] font-500 text-navy-700 transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">
                            <x-ui.icon name="download" class="size-4" /> Download
                        </a>
                        <button type="button" @click="close()" class="rounded-control border border-line px-3 py-1.5 font-body text-[13px] font-500 text-ink transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">Close</button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 bg-paper p-3">
                    <template x-if="kind === 'image'">
                        <img :src="url" :alt="name" class="mx-auto h-full w-full object-contain">
                    </template>
                    <template x-if="kind === 'pdf'">
                        <iframe :src="url" :title="name" class="h-full w-full rounded-control border border-line bg-surface"></iframe>
                    </template>
                    <template x-if="kind === 'other'">
                        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
                            <x-ui.icon name="form" class="size-8 text-mist" />
                            <p class="font-body text-[14px] text-ink">This file type can't be shown here.</p>
                            <p class="font-body text-[13px] text-slate">Download it to open it on your device.</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
