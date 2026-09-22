{{--
    Rows are provided via the default slot as <tr><td data-label="Column">value</td>...</tr>.
    The data-label attribute drives the mobile card collapse below 768px.
--}}
@props(['headers' => [], 'rows' => null, 'sortBy' => null, 'sortDirection' => 'asc'])

<div class="overflow-hidden rounded-card border border-line bg-surface">
    <table class="w-full
        [&_thead]:hidden md:[&_thead]:table-header-group
        [&_tr]:block md:[&_tr]:table-row
        [&_tr]:border-b [&_tr]:border-line [&_tr]:p-4 md:[&_tr]:border-0 md:[&_tr]:p-0
        [&_tbody_tr]:divide-y [&_tbody_tr]:divide-line md:[&_tbody_tr]:divide-y-0
        [&_td]:block md:[&_td]:table-cell
        [&_td]:py-1.5 md:[&_td]:px-4 md:[&_td]:py-3
        [&_td]:before:mr-2 [&_td]:before:font-body [&_td]:before:text-[11px] [&_td]:before:font-600 [&_td]:before:uppercase [&_td]:before:tracking-[0.06em] [&_td]:before:text-mist [&_td]:before:content-[attr(data-label)]
        md:[&_td]:before:hidden">
        <thead class="border-b border-line bg-paper">
            <tr>
                @foreach ($headers as $header)
                    <th class="px-4 py-3 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">
                        @if ($header['sortable'] ?? false)
                            <button
                                type="button"
                                wire:click="sort('{{ $header['key'] }}')"
                                class="inline-flex items-center gap-1 transition-colors hover:text-ink"
                            >
                                {{ $header['label'] }}
                                @if ($sortBy === $header['key'])
                                    <svg class="size-3 shrink-0 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m6 15 6-6 6 6" />
                                    </svg>
                                @endif
                            </button>
                        @else
                            {{ $header['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            {{ $slot }}
        </tbody>
    </table>

    @if ($rows && method_exists($rows, 'hasPages') && $rows->hasPages())
        <div class="border-t border-line px-4 py-3">
            {{ $rows->links() }}
        </div>
    @endif
</div>
