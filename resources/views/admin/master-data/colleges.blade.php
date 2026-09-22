<?php

use App\Models\College;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Colleges'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

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
        $this->reset(['editingId', 'code', 'name']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $college = College::findOrFail($id);

        $this->editingId = $college->id;
        $this->code = $college->code;
        $this->name = $college->name;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        College::updateOrCreate(['id' => $this->editingId], $validated);

        $this->successMessage = $this->editingId ? 'College updated.' : 'College added.';
        $this->showModal = false;
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $college = College::findOrFail($id);
        $college->update(['is_active' => ! $college->is_active]);
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:colleges,code,'.$this->editingId],
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    public function with(): array
    {
        return [
            'colleges' => College::query()
                ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                    ->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")
                ))
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
            <h1 class="font-display text-[30px] font-600 text-ink">Colleges</h1>
        </div>
        <x-ui.button wire:click="create">Add college</x-ui.button>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 max-w-sm">
        <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by code or name" />
    </div>

    <div class="mt-4">
        @if ($colleges->isEmpty())
            <x-ui.empty-state
                title="No colleges yet"
                message="Colleges appear here once added."
            >
                <x-slot:action>
                    <x-ui.button wire:click="create">Add college</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="[
                    ['key' => 'code', 'label' => 'Code', 'sortable' => true],
                    ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                    ['key' => 'is_active', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]"
                :rows="$colleges"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
            >
                @foreach ($colleges as $college)
                    <tr wire:key="college-{{ $college->id }}" class="transition hover:bg-paper">
                        <td data-label="Code" class="font-mono text-[14px] text-ink">{{ $college->code }}</td>
                        <td data-label="Name" class="text-[14px] text-ink">{{ $college->name }}</td>
                        <td data-label="Status"><x-ui.active-stamp :active="$college->is_active" /></td>
                        <td data-label="Actions" class="space-x-3 text-[14px]">
                            <button type="button" wire:click="edit({{ $college->id }})" class="text-forest-700 hover:underline">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $college->id }})" class="text-slate hover:underline">
                                {{ $college->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit college' : 'Add college'">
        <form wire:submit="save" class="space-y-5">
            <x-ui.input name="code" label="Code" wire:model="code" required maxlength="20" />
            <x-ui.input name="name" label="Name" wire:model="name" required maxlength="150" />

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save college</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
