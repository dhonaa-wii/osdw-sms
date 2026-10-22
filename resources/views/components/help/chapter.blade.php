@props(['id', 'number', 'title', 'icon'])

{{-- One chapter of the Help Center. In the PDF each chapter starts a new page. --}}
<section id="{{ $id }}" data-help-chapter x-show="chapterVisible(@js($id))" {{ $attributes->merge(['class' => 'pt-14 first:pt-0']) }}>
    <header class="flex items-center gap-4 border-b border-line pb-5">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-card bg-navy-600 text-white">
            <x-ui.icon :name="$icon" class="size-6" />
        </span>
        <div class="min-w-0">
            <p class="font-body text-[13px] font-500 text-navy-600">Chapter {{ $number }}</p>
            <h2 class="font-display text-[26px] font-600 leading-tight text-ink">{{ $title }}</h2>
        </div>
    </header>
    {{ $slot }}
</section>
