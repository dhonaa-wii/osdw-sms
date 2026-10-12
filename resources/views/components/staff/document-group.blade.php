@props(['title', 'icon', 'complied' => null, 'total' => null, 'verb' => 'complied'])

{{--
    One kind of document inside a review page's Documents card (application
    documents, additional, disbursement or renewal requirements): a slim
    header band with the count, then the documents in the slot.
--}}
<section {{ $attributes->class(['border-line [&:not(:first-child)]:border-t']) }}>
    <div class="flex items-center gap-2 border-b border-line bg-paper/70 px-4 py-2">
        <x-ui.icon :name="$icon" class="size-4 text-slate" />
        <h3 class="min-w-0 flex-1 truncate font-body text-[13px] font-600 text-ink">{{ $title }}</h3>
        @if ($total)
            <span class="inline-flex shrink-0 items-center gap-1.5 font-body text-[12px] {{ $complied === $total ? 'text-navy-700' : 'text-amber-600' }}">
                <x-ui.icon :name="$complied === $total ? 'check-circle' : 'clock'" class="size-3.5" />
                <span class="font-mono">{{ $complied }}/{{ $total }}</span> {{ $verb }}
            </span>
        @endif
    </div>
    <div class="divide-y divide-line">
        {{ $slot }}
    </div>
</section>
