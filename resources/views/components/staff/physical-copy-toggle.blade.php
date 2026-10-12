@props(['received' => false, 'action' => null, 'receivedAt' => null])

{{--
    "Physical copy received" next to a document. Pass the Livewire `action` to
    toggle it; without one it is a read-only marker.
--}}
@php
    $tone = $received ? 'border-navy-600/30 bg-navy-50 text-navy-700' : 'border-line text-slate';
    $title = $received ? 'Received'.($receivedAt ? ' '.$receivedAt->format('M j, Y') : '') : 'Physical copy not received yet';
@endphp

@if ($action)
    <label class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-control border px-2.5 font-body text-[13px] transition-colors hover:bg-paper max-sm:mr-auto {{ $tone }}" title="{{ $title }}">
        <input
            type="checkbox"
            class="rounded border-line text-navy-600 focus-visible:ring-2 focus-visible:ring-navy-600"
            wire:click="{{ $action }}"
            wire:loading.attr="disabled"
            wire:target="{{ $action }}"
            @checked($received)
        >
        Physical copy received
    </label>
@else
    <span class="inline-flex h-8 items-center gap-1.5 rounded-control border px-2.5 font-body text-[13px] max-sm:mr-auto {{ $tone }}" title="{{ $title }}">
        <x-ui.icon :name="$received ? 'check' : 'building'" class="size-3.5" />
        {{ $received ? 'Physical copy received' : 'Physical copy pending' }}
    </span>
@endif
