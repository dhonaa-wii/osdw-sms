<?php

use App\Enums\DegreeLevel;
use App\Models\College;
use App\Models\Program;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Programs'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $college_id = '';

    public string $code = '';

    public string $name = '';

    public string $degree_level = '';

    public ?string $successMessage = null;

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
        $this->reset(['editingId', 'college_id', 'code', 'name', 'degree_level']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $program = Program::findOrFail($id);

        $this->editingId = $program->id;
        $this->college_id = (string) $program->college_id;
        $this->code = $program->code;
        $this->name = $program->name;
        $this->degree_level = $program->degree_level->value;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'degree_level' => ['required', 'in:'.implode(',', array_column(DegreeLevel::cases(), 'value'))],
        ]);

        Program::updateOrCreate(['id' => $this->editingId], $validated);

        $this->successMessage = $this->editingId ? 'Program updated.' : 'Program added.';
        $this->showModal = false;
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $program = Program::findOrFail($id);
        $program->update(['is_active' => ! $program->is_active]);
    }

    public function with(): array
    {
        return [
            'programs' => Program::query()
                ->with('college')
                ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                    ->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")
                ))
                ->orderBy($this->sortBy, $this->sortDirection)
                ->paginate(10),
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <div class="mt-3 flex items-center justify-between">
        <div>
            <h1 class="font-display text-[30px] font-600 text-ink">Programs</h1>
        </div>
        <x-ui.button wire:click="create">Add program</x-ui.button>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 max-w-sm">
        <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by code or name" />
    </div>

    <div class="mt-4">
        @if ($programs->isEmpty())
            <x-ui.empty-state title="No programs yet" message="Programs appear here once added.">
                <x-slot:action>
                    <x-ui.button wire:click="create">Add program</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="[
                    ['key' => 'code', 'label' => 'Code', 'sortable' => true],
                    ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                    ['key' => 'college', 'label' => 'College', 'sortable' => false],
                    ['key' => 'degree_level', 'label' => 'Level', 'sortable' => false],
                    ['key' => 'is_active', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]"
                :rows="$programs"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
            >
                @foreach ($programs as $program)
                    <tr wire:key="program-{{ $program->id }}" class="transition hover:bg-paper">
                        <td data-label="Code" class="font-mono text-[14px] text-ink">{{ $program->code }}</td>
                        <td data-label="Name" class="text-[14px] text-ink">{{ $program->name }}</td>
                        <td data-label="College" class="text-[14px] text-slate">{{ $program->college->code }}</td>
                        <td data-label="Level" class="text-[14px] text-slate">{{ $program->degree_level->label() }}</td>
                        <td data-label="Status"><x-ui.active-stamp :active="$program->is_active" /></td>
                        <td data-label="Actions" class="space-x-3 text-[14px]">
                            <button type="button" wire:click="edit({{ $program->id }})" class="text-forest-700 hover:underline">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $program->id }})" class="text-slate hover:underline">
                                {{ $program->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit program' : 'Add program'">
        <form wire:submit="save" class="space-y-5">
            <x-ui.select name="college_id" label="College" wire:model="college_id" placeholder="Select a college" required>
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input name="code" label="Code" wire:model="code" required maxlength="30" />
            <x-ui.input name="name" label="Name" wire:model="name" required maxlength="200" />

            <x-ui.select name="degree_level" label="Degree level" wire:model="degree_level" placeholder="Select a level" required>
                @foreach (\App\Enums\DegreeLevel::cases() as $level)
                    <option value="{{ $level->value }}">{{ $level->label() }}</option>
                @endforeach
            </x-ui.select>

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save program</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
