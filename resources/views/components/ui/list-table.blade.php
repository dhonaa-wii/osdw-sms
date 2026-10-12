{{--
    Compact list table used inside a list card (see the admin list pages).
    Header cells go in the `head` slot, rows in the default slot. Under 768px
    each row collapses to a card: cells stack in the first column and the
    actions cell (col-start-2 row-span-N row-start-1) sits on the right.
--}}
<table {{ $attributes->merge(['class' => 'w-full
    [&_thead]:hidden md:[&_thead]:table-header-group
    [&_th]:px-4 [&_th]:py-2 [&_th]:text-left [&_th]:font-body [&_th]:text-[11px] [&_th]:font-600 [&_th]:uppercase [&_th]:tracking-[0.08em] [&_th]:text-mist
    [&_tbody_tr]:grid [&_tbody_tr]:grid-cols-[1fr_auto] [&_tbody_tr]:gap-x-3 [&_tbody_tr]:gap-y-1 [&_tbody_tr]:px-4 [&_tbody_tr]:py-3
    md:[&_tbody_tr]:table-row md:[&_tbody_tr]:p-0
    md:[&_td]:table-cell md:[&_td]:px-4 md:[&_td]:py-2.5 md:[&_td]:align-middle']) }}>
    <thead class="border-b border-line bg-paper/60">
        <tr>{{ $head }}</tr>
    </thead>
    <tbody class="divide-y divide-line">
        {{ $slot }}
    </tbody>
</table>
