@props(['url', 'name', 'mime' => null, 'label'])

{{--
    One uploaded document on a staff page: its label and status on top, the
    host's actions (verify, reject, physical copy) on the right, then the file
    itself shown in place. Extra content in the slot (e.g. a reject form) sits
    between the header and the preview.
--}}
<div {{ $attributes->class(['px-4 py-3.5']) }}>
    {{-- Name, then actions, then the full-screen button; under 640px the actions drop to their own row. --}}
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2.5">
        <div class="order-1 flex min-w-0 flex-1 items-center gap-2.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-control border border-line bg-paper text-slate">
                <x-ui.icon :name="str_starts_with((string) $mime, 'image/') ? 'image' : 'form'" class="size-4" />
            </span>
            <div class="min-w-0">
                <h3 class="truncate font-body text-[14px] font-600 text-ink">{{ $label }}</h3>
                @isset($meta)
                    <p class="flex min-w-0 items-center gap-1.5 font-body text-[12px]">{{ $meta }}</p>
                @endisset
            </div>
        </div>

        @if (isset($actions) && trim($actions) !== '')
            <div class="order-3 flex flex-wrap items-center justify-end gap-1.5 max-sm:w-full sm:order-2">
                {{ $actions }}
            </div>
        @endif

        <x-ui.document-link
            :url="$url"
            :name="$name"
            :mime="$mime"
            :title="$label"
            class="order-2 flex size-8 shrink-0 items-center justify-center rounded-control border border-line text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 sm:order-3"
            aria-label="Open {{ $label }} full screen"
            title="Open full screen"
        >
            <x-ui.icon name="expand" class="size-4" />
        </x-ui.document-link>
    </div>

    {{ $slot }}

    <x-ui.document-preview class="mt-3" :url="$url" :name="$name" :mime="$mime" />
</div>
