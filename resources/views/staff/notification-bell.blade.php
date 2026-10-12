<?php

use App\Services\StudentNotificationService;
use Livewire\Component;

/**
 * The bell in the staff and administrators' header, built like the student
 * one. Checks every minute; each check also sends any due registration
 * notices, so "now open" and "registration closed" reach staff without a
 * scheduler.
 */
new class extends Component
{
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('staff.notifications'));
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function with(StudentNotificationService $notifications): array
    {
        $notifications->syncRegistrationNotices();

        $user = auth()->user();

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->take(8)->get(),
        ];
    }
};
?>

<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" wire:poll.60s>
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open.toString()"
        aria-haspopup="true"
        aria-label="Notifications{{ $unreadCount ? ", {$unreadCount} unread" : '' }}"
        class="relative flex size-9 items-center justify-center rounded-control text-slate transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
    >
        <x-ui.icon name="bell" class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-crimson-600 px-1 font-mono text-[10px] font-500 leading-4 text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-on:click.outside="open = false"
        x-transition:enter="transition duration-150 ease-out"
        x-transition:enter-start="-translate-y-1 opacity-0"
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="fixed inset-x-4 top-16 z-30 overflow-hidden rounded-card border border-line bg-surface shadow-lg sm:absolute sm:inset-x-auto sm:right-0 sm:top-11 sm:w-96"
    >
        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-2.5">
            <p class="font-display text-[15px] font-600 text-ink">Notifications</p>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="rounded-control font-body text-[12px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Mark all as read</button>
            @endif
        </div>

        @if ($recent->isEmpty())
            <p class="px-4 py-8 text-center font-body text-[13px] text-slate">No notifications yet. New programs, registration openings and closings, and interviews show up here.</p>
        @else
            <div class="max-h-[60vh] divide-y divide-line overflow-y-auto">
                @foreach ($recent as $notification)
                    <div wire:key="bell-{{ $notification->id }}"><x-ui.notification-item :notification="$notification" compact /></div>
                @endforeach
            </div>
        @endif

        <a href="{{ route('staff.notifications') }}" x-on:click="open = false" class="block border-t border-line px-4 py-2.5 text-center font-body text-[13px] font-500 text-navy-700 hover:bg-paper focus-visible:bg-paper focus-visible:outline-none">View all notifications</a>
    </div>
</div>
