@props(['show', 'submit', 'id', 'label', 'placeholder', 'cancel', 'loading'])

{{--
    The inline "why" field under a document being rejected or returned. The
    host's Alpine scope holds `remarks` and the open flag named in `show`.
--}}
<form
    x-show="{{ $show }}"
    x-cloak
    x-on:submit.prevent="{{ $submit }}"
    class="mt-3 flex flex-wrap items-center gap-2 rounded-control border border-crimson-600/25 bg-crimson-600/5 p-2"
>
    <label for="{{ $id }}" class="sr-only">{{ $label }}</label>
    <input id="{{ $id }}" x-ref="reason" type="text" x-model="remarks" placeholder="{{ $placeholder }}" class="h-8 min-w-0 flex-1 basis-48 rounded-control border border-line bg-surface px-2.5 font-body text-[13px] focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25">
    <div class="flex gap-2 max-sm:w-full max-sm:justify-end">
        <x-ui.button variant="secondary" class="h-8 px-3 py-0 text-[13px]" x-on:click="{{ $cancel }}">Cancel</x-ui.button>
        <x-ui.button type="submit" variant="danger" class="h-8 px-3 py-0 text-[13px]" :loading="$loading" x-bind:disabled="remarks.trim() === ''">{{ $slot }}</x-ui.button>
    </div>
</form>
