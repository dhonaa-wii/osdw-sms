<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Notifications'))] class extends Component
{
    use WithPagination;

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function with(): array
    {
        return [
            'notifications' => auth()->user()->notifications()->paginate(20),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ];
    }
};
?>

<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-display text-[26px] font-600 text-ink">Notifications</h1>
        @if ($unreadCount > 0)
            <x-ui.button variant="secondary" wire:click="markAllRead" loading="markAllRead">Mark all as read</x-ui.button>
        @endif
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        @if ($notifications->isEmpty())
            <x-ui.list-empty icon="bell" title="No notifications yet" message="New programs, registration openings and closings, and interviews show up here.">
                <x-ui.button-link :href="route('staff.applications.index')">Go to applications</x-ui.button-link>
            </x-ui.list-empty>
        @else
            <div class="divide-y divide-line">
                @foreach ($notifications as $notification)
                    <div wire:key="notification-{{ $notification->id }}"><x-ui.notification-item :notification="$notification" /></div>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div class="border-t border-line px-4 py-3">{{ $notifications->links() }}</div>
            @endif
        @endif
    </div>
</div>
