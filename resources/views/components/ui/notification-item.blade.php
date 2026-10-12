{{-- One bell notification as a button; the parent component's open() marks it read and follows its link. --}}
@props(['notification', 'compact' => false])

@php
    $kind = \App\Enums\NotificationKind::tryFrom($notification->data['kind'] ?? '') ?? \App\Enums\NotificationKind::StatusChanged;
    $unread = $notification->read_at === null;
@endphp

<button
    type="button"
    wire:click="open('{{ $notification->id }}')"
    wire:loading.attr="disabled"
    wire:target="open('{{ $notification->id }}')"
    class="flex w-full items-start gap-3 text-left transition-colors hover:bg-paper focus-visible:bg-paper focus-visible:outline-none disabled:opacity-60 {{ $compact ? 'px-4 py-3' : 'px-5 py-3.5' }}"
>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $kind->iconClasses() }}">
        <x-ui.icon :name="$kind->icon()" class="size-4" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="block font-body text-[13px] {{ $unread ? 'font-600 text-ink' : 'font-500 text-slate' }}">{{ $notification->data['title'] ?? '' }}</span>
        @if (! empty($notification->data['body']))
            <span class="mt-0.5 block font-body text-[12px] text-slate {{ $compact ? 'line-clamp-2' : '' }}">{{ $notification->data['body'] }}</span>
        @endif
        <span class="mt-1 block font-body text-[11px] text-mist">{{ $notification->created_at->diffForHumans() }}</span>
    </span>
    @if ($unread)
        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-navy-600"><span class="sr-only">Unread</span></span>
    @endif
</button>
