<?php

use App\Enums\Semester;
use App\Models\AcademicTerm;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Academic Terms'))] class extends Component
{
    use WithPagination;

    public string $sortBy = 'start_date';

    public string $sortDirection = 'desc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $school_year = '';

    public string $semester = '';

    public string $start_date = '';

    public string $end_date = '';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function create(): void
    {
        $this->reset(['editingId', 'school_year', 'semester', 'start_date', 'end_date']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $term = AcademicTerm::findOrFail($id);

        $this->editingId = $term->id;
        $this->school_year = $term->school_year;
        $this->semester = $term->semester->value;
        $this->start_date = $term->start_date->format('Y-m-d');
        $this->end_date = $term->end_date->format('Y-m-d');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'school_year' => ['required', 'string', 'max:9'],
            'semester' => ['required', 'in:'.implode(',', array_column(Semester::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        AcademicTerm::updateOrCreate(['id' => $this->editingId], $validated);

        $this->dispatch('notice', title: $this->editingId ? 'Academic term updated' : 'Academic term added');
        $this->showModal = false;
        $this->resetPage();
    }

    public function setCurrent(int $id): void
    {
        AcademicTerm::where('is_current', true)->update(['is_current' => false]);
        AcademicTerm::whereKey($id)->update(['is_current' => true]);
    }

    public function with(): array
    {
        return [
            'terms' => AcademicTerm::query()
                ->orderBy($this->sortBy, $this->sortDirection)
                ->paginate(10),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <div class="mt-3 flex items-center justify-between">
        <div>
            <h1 class="font-display text-[30px] font-600 text-ink">Academic terms</h1>
        </div>
        <x-ui.button wire:click="create">Add term</x-ui.button>
    </div>

    <div class="mt-6">
        @if ($terms->isEmpty())
            <x-ui.empty-state title="No academic terms yet" message="Terms appear here once added.">
                <x-slot:action>
                    <x-ui.button wire:click="create">Add term</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="[
                    ['key' => 'school_year', 'label' => 'School year', 'sortable' => true],
                    ['key' => 'semester', 'label' => 'Semester', 'sortable' => false],
                    ['key' => 'start_date', 'label' => 'Start', 'sortable' => true],
                    ['key' => 'end_date', 'label' => 'End', 'sortable' => true],
                    ['key' => 'is_current', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]"
                :rows="$terms"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
            >
                @foreach ($terms as $term)
                    <tr wire:key="term-{{ $term->id }}" class="transition hover:bg-paper">
                        <td data-label="School year" class="font-mono text-[14px] text-ink">{{ $term->school_year }}</td>
                        <td data-label="Semester" class="text-[14px] text-ink">{{ $term->semester->label() }}</td>
                        <td data-label="Start" class="font-mono text-[14px] text-slate">{{ $term->start_date->format('M j, Y') }}</td>
                        <td data-label="End" class="font-mono text-[14px] text-slate">{{ $term->end_date->format('M j, Y') }}</td>
                        <td data-label="Status">
                            @if ($term->is_current)
                                <span class="inline-flex items-center gap-2 border-l-2 border-gold-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-gold-600">Current</span>
                            @else
                                <span class="inline-flex items-center gap-2 border-l-2 border-mist pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Not current</span>
                            @endif
                        </td>
                        <td data-label="Actions" class="space-x-3 text-[14px]">
                            <button type="button" wire:click="edit({{ $term->id }})" class="text-navy-700 hover:underline">Edit</button>
                            @unless ($term->is_current)
                                <button type="button" wire:click="setCurrent({{ $term->id }})" class="text-slate hover:underline">Set as current</button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit academic term' : 'Add academic term'">
        <form wire:submit="save" class="space-y-5">
            <x-ui.input name="school_year" label="School year" wire:model="school_year" required maxlength="9" placeholder="2025-2026" />

            <x-ui.select name="semester" label="Semester" wire:model="semester" placeholder="Select a semester" required>
                @foreach (\App\Enums\Semester::cases() as $semesterOption)
                    <option value="{{ $semesterOption->value }}">{{ $semesterOption->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input name="start_date" label="Start date" type="date" wire:model="start_date" required />
            <x-ui.input name="end_date" label="End date" type="date" wire:model="end_date" required />

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save term</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
