<?php

use App\Enums\FundingSource;
use App\Models\Provider;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Providers'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:200')]
    public string $name = '';

    #[Validate(['required', new Enum(FundingSource::class)])]
    public string $funding_source = '';

    #[Validate('nullable|string|max:150')]
    public string $contact_person = '';

    #[Validate('nullable|email|max:150')]
    public string $contact_email = '';

    #[Validate('nullable|numeric|min:0')]
    public string $allocated_budget = '';

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
        $this->reset(['editingId', 'name', 'funding_source', 'contact_person', 'contact_email', 'allocated_budget']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $provider = Provider::findOrFail($id);

        $this->editingId = $provider->id;
        $this->name = $provider->name;
        $this->funding_source = $provider->funding_source->value;
        $this->contact_person = $provider->contact_person ?? '';
        $this->contact_email = $provider->contact_email ?? '';
        $this->allocated_budget = $provider->allocated_budget !== null ? (string) $provider->allocated_budget : '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $validated['contact_person'] = $validated['contact_person'] ?: null;
        $validated['contact_email'] = $validated['contact_email'] ?: null;
        $validated['allocated_budget'] = $validated['allocated_budget'] !== '' ? $validated['allocated_budget'] : null;

        Provider::updateOrCreate(['id' => $this->editingId], $validated);

        $this->successMessage = $this->editingId ? 'Provider updated.' : 'Provider added.';
        $this->showModal = false;
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $provider = Provider::findOrFail($id);
        $provider->update(['is_active' => ! $provider->is_active]);
    }

    public function with(): array
    {
        return [
            'providers' => Provider::query()
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
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
            <h1 class="font-display text-[30px] font-600 text-ink">Providers</h1>
        </div>
        <x-ui.button wire:click="create">Add provider</x-ui.button>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 max-w-sm">
        <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by name" />
    </div>

    <div class="mt-4">
        @if ($providers->isEmpty())
            <x-ui.empty-state title="No providers yet" message="Providers appear here once added.">
                <x-slot:action>
                    <x-ui.button wire:click="create">Add provider</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="[
                    ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                    ['key' => 'funding_source', 'label' => 'Funding source', 'sortable' => false],
                    ['key' => 'contact_person', 'label' => 'Contact', 'sortable' => false],
                    ['key' => 'is_active', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]"
                :rows="$providers"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
            >
                @foreach ($providers as $provider)
                    <tr wire:key="provider-{{ $provider->id }}" class="transition hover:bg-paper">
                        <td data-label="Name" class="text-[14px] text-ink">{{ $provider->name }}</td>
                        <td data-label="Funding source" class="text-[14px] text-slate">{{ $provider->funding_source->label() }}</td>
                        <td data-label="Contact" class="text-[14px] text-slate">
                            {{ $provider->contact_person ?? '—' }}
                            @if ($provider->contact_email)
                                <br><span class="text-[13px]">{{ $provider->contact_email }}</span>
                            @endif
                        </td>
                        <td data-label="Status"><x-ui.active-stamp :active="$provider->is_active" /></td>
                        <td data-label="Actions" class="space-x-3 text-[14px]">
                            <a href="{{ route('admin.providers.view', $provider) }}" class="text-forest-700 hover:underline">View</a>
                            <button type="button" wire:click="edit({{ $provider->id }})" class="text-forest-700 hover:underline">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $provider->id }})" class="text-slate hover:underline">
                                {{ $provider->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit provider' : 'Add provider'">
        <form wire:submit="save" class="space-y-5">
            <x-ui.input name="name" label="Name" wire:model="name" required maxlength="200" />

            <x-ui.select name="funding_source" label="Funding source" wire:model="funding_source" placeholder="Select a funding source" required>
                @foreach (FundingSource::cases() as $fundingSourceOption)
                    <option value="{{ $fundingSourceOption->value }}">{{ $fundingSourceOption->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input name="contact_person" label="Contact person" wire:model="contact_person" maxlength="150" />
            <x-ui.input name="contact_email" label="Contact email" type="email" wire:model="contact_email" maxlength="150" />
            <x-ui.input name="allocated_budget" label="Allocated budget" type="number" step="0.01" min="0" wire:model="allocated_budget" />

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save provider</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
