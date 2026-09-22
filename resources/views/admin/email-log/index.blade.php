<?php

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\EmailLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Email Log'))] class extends Component
{
    use WithPagination;

    public string $status = '';

    public string $type = '';

    public string $search = '';

    public function with(): array
    {
        return [
            'logs' => EmailLog::query()
                ->with('application.applicant')
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->when($this->type, fn ($query) => $query->where('type', $this->type))
                ->when($this->search, fn ($query) => $query->where('recipient', 'like', "%{$this->search}%"))
                ->orderByDesc('created_at')
                ->paginate(20),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <h1 class="mt-3 font-display text-[30px] font-600 text-ink">Email log</h1>

    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by recipient email" />
        <x-ui.select name="type" wire:model.live="type" placeholder="Any type">
            @foreach (EmailType::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select name="status" wire:model.live="status" placeholder="Any status">
            @foreach (EmailStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </x-ui.select>
    </div>

    <div class="mt-6">
        @if ($logs->isEmpty())
            <x-ui.empty-state title="No emails match" message="Try a wider filter." />
        @else
            <x-ui.data-table :headers="[
                ['key' => 'when', 'label' => 'When', 'sortable' => false],
                ['key' => 'recipient', 'label' => 'Recipient', 'sortable' => false],
                ['key' => 'type', 'label' => 'Type', 'sortable' => false],
                ['key' => 'subject', 'label' => 'Subject', 'sortable' => false],
                ['key' => 'status', 'label' => 'Status', 'sortable' => false],
            ]" :rows="$logs">
                @foreach ($logs as $log)
                    <tr wire:key="email-log-{{ $log->id }}" class="transition hover:bg-paper">
                        <td data-label="When" class="font-mono text-[13px] text-slate">{{ $log->created_at->format('M j, Y g:ia') }}</td>
                        <td data-label="Recipient" class="font-mono text-[13px] text-ink">{{ $log->recipient }}</td>
                        <td data-label="Type" class="text-[14px] text-slate">{{ $log->type->label() }}</td>
                        <td data-label="Subject" class="text-[14px] text-ink">{{ $log->subject }}</td>
                        <td data-label="Status">
                            <x-ui.status-stamp :status="$log->status" title="{{ $log->status === EmailStatus::Failed ? $log->error_message : null }}" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>
</div>
