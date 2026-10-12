@props(['url', 'name', 'mime' => null])

{{--
    An uploaded file shown in place at full height, so the page scrolls from
    one document to the next. Images render as-is; PDFs are drawn page by page
    with pdf.js (window.renderPdfPreview in app.js) so they read the same on
    phones. Loads shortly before it scrolls into view.
--}}
@php
    $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
    $kind = match (true) {
        str_starts_with((string) $mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) => 'image',
        $mime === 'application/pdf' || $extension === 'pdf' => 'pdf',
        default => 'other',
    };
@endphp

<div
    {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-control border border-line bg-paper']) }}
    wire:ignore
    x-data="{
        state: 'idle',
        pages: 0,
        async load() {
            if (this.state !== 'idle') return;
            this.state = 'loading';
            if (@js($kind) !== 'pdf') return;
            try {
                while (! window.renderPdfPreview) await new Promise((resolve) => setTimeout(resolve, 50));
                this.pages = await window.renderPdfPreview(this.$refs.pages, @js($url));
                this.state = 'ready';
            } catch (error) {
                this.state = 'failed';
            }
        },
    }"
    x-intersect.once.margin.600px="load()"
>
    <div class="p-3" x-bind:class="state === 'ready' || @js($kind) === 'other' ? '' : 'min-h-64'" aria-label="Preview of {{ $name }}">
        @if ($kind === 'image')
            <template x-if="state !== 'idle'">
                <img src="{{ $url }}" alt="{{ $name }}" class="mx-auto block h-auto w-full max-w-full rounded-control border border-line bg-white shadow-sm shadow-ink/5" x-on:load="state = 'ready'" x-on:error="state = 'failed'">
            </template>
        @elseif ($kind === 'pdf')
            <div x-ref="pages" class="space-y-3" x-show="state === 'ready'"></div>
        @endif

        @if ($kind === 'other')
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-10 text-center">
                <x-ui.icon name="form" class="size-8 text-mist" />
                <p class="font-body text-[14px] text-ink">This file type can't be shown here.</p>
                <a href="{{ $url }}" download="{{ $name }}" class="inline-flex items-center gap-1.5 rounded-control border border-line bg-surface px-3 py-1.5 font-body text-[13px] font-500 text-navy-700 transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                    <x-ui.icon name="download" class="size-4" /> Download to open it
                </a>
            </div>
        @else
            <div x-show="state === 'idle' || state === 'loading'" class="absolute inset-0 flex items-center justify-center gap-2 font-body text-[13px] text-slate">
                <x-ui.spinner class="size-4" /> Loading preview
            </div>
            <div x-show="state === 'failed'" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center">
                <x-ui.icon name="alert-circle" class="size-7 text-mist" />
                <p class="font-body text-[14px] text-ink">The preview couldn't load.</p>
                <a href="{{ $url }}" download="{{ $name }}" class="inline-flex items-center gap-1.5 rounded-control border border-line bg-surface px-3 py-1.5 font-body text-[13px] font-500 text-navy-700 transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                    <x-ui.icon name="download" class="size-4" /> Download the file
                </a>
            </div>
        @endif
    </div>

</div>
