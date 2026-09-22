@props(['offerings'])

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button
        type="button"
        @click="open = ! open"
        class="relative flex items-center gap-1.5 rounded-control border border-line bg-surface px-2.5 py-1.5 font-body text-[12px] font-500 text-slate transition hover:border-forest-600 hover:text-ink"
    >
        <svg class="size-4 {{ $offerings->isNotEmpty() ? 'text-amber-600' : 'text-mist' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.3 3.9 2.6 17.3a1.5 1.5 0 0 0 1.3 2.2h16.2a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.6 0Z" />
        </svg>
        Attention
        @if ($offerings->isNotEmpty())
            <span class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-crimson-600" aria-hidden="true"></span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute right-0 z-10 mt-2 w-72 rounded-card border border-line bg-surface p-3 shadow-md"
    >
        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Applications at capacity</p>
        @if ($offerings->isEmpty())
            <p class="mt-2 font-body text-[13px] text-slate">Nothing needs attention right now.</p>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($offerings as $offering)
                    <li class="font-body text-[13px] text-ink">
                        {{ $offering->scholarshipProgram->name }}
                        <span class="block font-mono text-[12px] text-amber-600">{{ $offering->applications_count }} / {{ $offering->max_applications }} applications &mdash; registration closed early</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
