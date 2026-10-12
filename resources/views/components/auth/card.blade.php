{{--
    The frame shared by the sign-in pages (login, register, forgot password,
    profile setup): logo, heading, one card, and an optional `footer` slot
    for the links under it.
--}}
@props(['title', 'width' => 'max-w-sm'])

@php $logoUrl = \App\Models\BrandingImage::url(\App\Enums\BrandingImageKey::Logo); @endphp

<div class="flex min-h-[calc(100vh-10rem)] items-center justify-center">
    <div class="w-full {{ $width }}">
        <div class="flex flex-col items-center text-center">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="OSDW logo" class="size-14 rounded-full object-contain">
            @else
                <span class="flex size-14 items-center justify-center rounded-full bg-navy-600 text-white">
                    <x-ui.icon name="graduation" class="size-7" />
                </span>
            @endif
            <h1 class="mt-3 font-display text-[22px] font-600 text-ink">{{ $title }}</h1>
        </div>

        <div class="mt-6 rounded-card border border-line bg-surface p-5 shadow-sm sm:p-6">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="mt-4 space-y-1 text-center font-body text-[13px] text-slate">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
