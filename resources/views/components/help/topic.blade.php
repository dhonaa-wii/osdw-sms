@props(['id', 'title'])

{{-- A topic inside a chapter: the unit the contents list, the search and the active-section tracking work on. --}}
<article
    id="{{ $id }}"
    data-help-topic
    x-show="topicVisible(@js($id))"
    {{ $attributes->merge(['class' => 'pt-10 font-body text-[15px] leading-7 text-slate [&_p+p]:mt-3 [&_strong]:font-600 [&_strong]:text-ink [&>ul]:mt-3 [&>ul]:list-disc [&>ul]:space-y-1.5 [&>ul]:pl-5 [&>ul>li]:marker:text-mist [&_h4]:mt-6 [&_h4]:font-display [&_h4]:text-[16px] [&_h4]:font-600 [&_h4]:text-ink [&_h4+p]:mt-1']) }}
>
    <h3 class="mb-3 font-display text-[20px] font-600 leading-snug text-ink">
        <a href="#{{ $id }}" class="group inline-flex items-center gap-2 rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
            {{ $title }}
            <x-ui.icon name="link" class="size-4 text-mist opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100" />
        </a>
    </h3>
    {{ $slot }}
</article>
