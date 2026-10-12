<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Audit Log'))] class extends Component
{
    use WithPagination;

    public string $user_id = '';

    public string $action = '';

    public string $auditable_type = '';

    public string $date_from = '';

    public string $date_to = '';

    protected array $auditableTypes = [
        'App\\Models\\Application' => 'Application',
        'App\\Models\\Award' => 'Award',
        'App\\Models\\ApplicationDocument' => 'Document',
        'App\\Models\\ApplicationAdditionalRequirement' => 'Additional requirement',
    ];

    /** Any filter change starts the list over from the first page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['user_id', 'action', 'auditable_type', 'date_from', 'date_to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('user_id', 'action', 'auditable_type', 'date_from', 'date_to');
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'logs' => AuditLog::query()
                ->with('user')
                ->when($this->user_id, fn ($query) => $query->where('user_id', $this->user_id))
                ->when($this->action, fn ($query) => $query->where('action', $this->action))
                ->when($this->auditable_type, fn ($query) => $query->where('auditable_type', $this->auditable_type))
                ->when($this->date_from, fn ($query) => $query->whereDate('created_at', '>=', $this->date_from))
                ->when($this->date_to, fn ($query) => $query->whereDate('created_at', '<=', $this->date_to))
                ->orderByDesc('created_at')
                ->paginate(20),
            'users' => User::staff()->orderBy('name')->get(),
            'auditableTypes' => $this->auditableTypes,
            'filtered' => $this->user_id !== '' || $this->action !== '' || $this->auditable_type !== '' || $this->date_from !== '' || $this->date_to !== '',
        ];
    }
};
?>

@php
    $dateInput = 'block h-10 w-full rounded-control border border-line bg-surface pl-9 pr-3 font-body text-[14px] text-ink transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25';
@endphp

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Audit log</h1>
        @if ($logs->total() > 0)
            <p class="flex items-center gap-1.5 font-body text-[13px] text-slate">
                <x-ui.icon name="clipboard-check" class="size-4 text-mist" />
                <span class="font-mono text-ink">{{ number_format($logs->total()) }}</span> {{ str('entry')->plural($logs->total()) }}
            </p>
        @endif
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-2 border-b border-line p-3 md:flex-row md:flex-wrap md:items-center">
            <x-ui.filter-select name="user_id" label="User" icon="user" wire:model.live="user_id" class="md:w-48">
                <option value="">Any user</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </x-ui.filter-select>

            <x-ui.filter-select name="action" label="Action" icon="sliders" wire:model.live="action" class="md:w-44">
                <option value="">Any action</option>
                @foreach (AuditAction::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </x-ui.filter-select>

            <x-ui.filter-select name="auditable_type" label="Record type" icon="layers" wire:model.live="auditable_type" class="md:w-52">
                <option value="">Any record type</option>
                @foreach ($auditableTypes as $class => $label)
                    <option value="{{ $class }}">{{ $label }}</option>
                @endforeach
            </x-ui.filter-select>

            <div class="flex items-center gap-2">
                <div class="relative flex-1 md:w-40 md:flex-none">
                    <label for="date_from" class="sr-only">From date</label>
                    <x-ui.icon name="calendar" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                    <input id="date_from" type="date" wire:model.live="date_from" max="{{ $date_to ?: null }}" class="{{ $dateInput }}">
                </div>
                <span class="font-body text-[13px] text-mist" aria-hidden="true">to</span>
                <div class="relative flex-1 md:w-40 md:flex-none">
                    <label for="date_to" class="sr-only">To date</label>
                    <x-ui.icon name="calendar" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                    <input id="date_to" type="date" wire:model.live="date_to" min="{{ $date_from ?: null }}" class="{{ $dateInput }}">
                </div>
            </div>

            @if ($filtered)
                <x-ui.button variant="ghost" class="px-2.5 py-1.5 text-[13px] md:ml-auto" loading="clearFilters" wire:click="clearFilters">
                    <x-ui.icon name="x" class="size-3.5" />
                    Clear filters
                </x-ui.button>
            @endif
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="user_id, action, auditable_type, date_from, date_to, clearFilters, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($logs->isEmpty())
                @if ($filtered)
                    <x-ui.list-empty icon="search" title="No matching activity" message="Try a wider filter or a different date range.">
                        <x-ui.button variant="secondary" wire:click="clearFilters">Clear filters</x-ui.button>
                    </x-ui.list-empty>
                @else
                    <x-ui.list-empty icon="clipboard-check" title="No activity recorded yet" message="Decisions on applications, documents and awards are logged here as staff make them." />
                @endif
            @else
                <x-ui.list-table>
                    <x-slot:head>
                        <th class="md:w-px md:whitespace-nowrap">When</th>
                        <th>User</th>
                        <th class="md:w-px md:whitespace-nowrap">Action</th>
                        <th>Record</th>
                        <th class="md:w-px md:whitespace-nowrap">IP address</th>
                    </x-slot:head>

                    @foreach ($logs as $log)
                        @php $userName = $log->user->name ?? 'System'; @endphp
                        <tr wire:key="log-{{ $log->id }}" class="transition-colors hover:bg-paper/60">
                            <td class="col-start-1 md:whitespace-nowrap">
                                <p class="font-mono text-[12px] text-ink">{{ $log->created_at->format('M j, Y') }}</p>
                                <p class="font-mono text-[11px] text-mist">{{ $log->created_at->format('g:i A') }}</p>
                            </td>
                            <td class="col-start-1 min-w-0">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-paper font-display text-[11px] font-600 text-slate" aria-hidden="true">
                                        {{ mb_strtoupper(collect(explode(' ', $userName))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('')) }}
                                    </span>
                                    <span class="truncate font-body text-[14px] text-ink">{{ $userName }}</span>
                                </div>
                            </td>
                            <td class="col-start-1 md:whitespace-nowrap"><x-ui.status-stamp :status="$log->action" /></td>
                            <td class="col-start-1 font-body text-[13px] text-slate">
                                {{ $auditableTypes[$log->auditable_type] ?? class_basename($log->auditable_type) }}
                                <span class="font-mono text-[12px] text-mist">#{{ $log->auditable_id }}</span>
                            </td>
                            <td class="col-start-1 font-mono text-[12px] text-mist md:whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-ui.list-table>

                @if ($logs->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
