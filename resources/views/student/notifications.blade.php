<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public', array('title' => 'Notifications', 'noindex' => true))] class extends Component
{
    use WithPagination;

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('student.applications.index'), navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('toast', message: 'All notifications marked as read.');
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
        <h1 class="font-display text-[24px] font-600 text-ink">Notifications</h1>
        @if ($unreadCount > 0)
            <x-ui.button variant="secondary" wire:click="markAllRead" loading="markAllRead" class="h-9">Mark all as read</x-ui.button>
        @endif
    </div>

    <div class="mt-4">
        @if ($notifications->isEmpty())
            <x-ui.empty-state title="No notifications yet" message="Updates on your applications, announcements and newly opened scholarships show up here.">
                <x-slot:action>
                    <x-ui.button-link :href="route('programs.index')" wire:navigate>Browse scholarships</x-ui.button-link>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
                @foreach ($notifications as $notification)
                    <div wire:key="notification-{{ $notification->id }}"><x-ui.notification-item :notification="$notification" /></div>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div class="mt-6">{{ $notifications->links() }}</div>
            @endif
        @endif
    </div>
</div>
