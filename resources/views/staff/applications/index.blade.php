<?php

use App\Models\ProgramOffering;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Applicants'))] class extends Component
{
    public function with(): array
    {
        return [
            'offerings' => ProgramOffering::query()
                ->where('opens_at', '<=', now())
                ->withCount('applications')
                ->with('scholarshipProgram', 'academicTerm')
                ->orderByDesc('closes_at')
                ->get(),
        ];
    }
};
?>

<div>
    <h1 class="font-display text-[30px] font-600 text-ink">Applicants</h1>

    <div class="mt-6">
        @if ($offerings->isEmpty())
            <x-ui.empty-state title="No offerings to review" message="Offerings appear here once they're open or closed to applications." />
        @else
            <x-ui.data-table :headers="[
                ['key' => 'program', 'label' => 'Program', 'sortable' => false],
                ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                ['key' => 'applications', 'label' => 'Applications', 'sortable' => false],
                ['key' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$offerings">
                @foreach ($offerings as $offering)
                    <tr wire:key="offering-{{ $offering->id }}" class="transition hover:bg-paper">
                        <td data-label="Program" class="text-[14px] text-ink">
                            {{ $offering->scholarshipProgram->name }}
                            <p class="font-body text-[12px] text-mist">{{ $offering->displayName() }}</p>
                        </td>
                        <td data-label="Status" class="text-[14px] text-slate">{{ $offering->registrationStatus()->label() }}</td>
                        <td data-label="Applications" class="font-mono text-[14px] text-ink">{{ $offering->applications_count }}</td>
                        <td data-label="Actions" class="text-[14px]">
                            <a href="{{ route('staff.applications.workspace', $offering) }}" class="font-body font-500 text-forest-700 hover:underline">Review</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>
</div>
