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
        'App\\Models\\ScholarTerm' => 'Scholar Term',
    ];

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
            'users' => User::orderBy('name')->get(),
            'auditableTypes' => $this->auditableTypes,
        ];
    }
};
?>

<div>
    <h1 class="font-display text-[30px] font-600 text-ink">Audit log</h1>

    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.select name="user_id" wire:model.live="user_id" placeholder="Any user">
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select name="action" wire:model.live="action" placeholder="Any action">
            @foreach (AuditAction::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select name="auditable_type" wire:model.live="auditable_type" placeholder="Any record type">
            @foreach ($auditableTypes as $class => $label)
                <option value="{{ $class }}">{{ $label }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input name="date_from" type="date" wire:model.live="date_from" placeholder="From" />
        <x-ui.input name="date_to" type="date" wire:model.live="date_to" placeholder="To" />
    </div>

    <div class="mt-6">
        @if ($logs->isEmpty())
            <x-ui.empty-state title="No matching activity" message="Try a wider filter or a different date range." />
        @else
            <x-ui.data-table :headers="[
                ['key' => 'when', 'label' => 'When', 'sortable' => false],
                ['key' => 'user', 'label' => 'User', 'sortable' => false],
                ['key' => 'action', 'label' => 'Action', 'sortable' => false],
                ['key' => 'record', 'label' => 'Record', 'sortable' => false],
                ['key' => 'ip', 'label' => 'IP', 'sortable' => false],
            ]" :rows="$logs">
                @foreach ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="transition hover:bg-paper">
                        <td data-label="When" class="font-mono text-[13px] text-slate">{{ $log->created_at->format('M j, Y g:ia') }}</td>
                        <td data-label="User" class="text-[14px] text-ink">{{ $log->user->name ?? 'System' }}</td>
                        <td data-label="Action" class="text-[14px]"><x-ui.status-stamp :status="$log->action" /></td>
                        <td data-label="Record" class="text-[14px] text-slate">{{ $auditableTypes[$log->auditable_type] ?? class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                        <td data-label="IP" class="font-mono text-[12px] text-mist">{{ $log->ip_address }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>
</div>
