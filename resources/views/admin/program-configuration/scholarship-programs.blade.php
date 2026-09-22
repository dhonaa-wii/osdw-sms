<?php

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Scholarship Programs'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $provider_id = '';

    public string $category = '';

    public string $description = '';

    public ?string $successMessage = null;

    public bool $showSavedModal = false;

    public ?int $savedProgramId = null;

    public bool $showProviderModal = false;

    public string $newProviderName = '';

    public string $newProviderFundingSource = '';

    public string $newProviderContactPerson = '';

    public string $newProviderContactEmail = '';

    public string $newProviderAllocatedBudget = '';

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
        $this->reset([
            'editingId', 'name', 'provider_id', 'category', 'description',
        ]);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $program = ScholarshipProgram::findOrFail($id);

        $this->editingId = $program->id;
        $this->name = $program->name;
        $this->provider_id = (string) $program->provider_id;
        $this->category = $program->category->value;
        $this->description = (string) $program->description;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'provider_id' => ['required', 'exists:providers,id'],
            'category' => ['required', 'in:'.implode(',', array_column(ScholarshipCategory::cases(), 'value'))],
            'description' => ['nullable', 'string'],
        ];

        $validated = $this->validate($rules);
        $isNew = $this->editingId === null;

        $programId = DB::transaction(function () use ($validated): int {
            $program = ScholarshipProgram::updateOrCreate(['id' => $this->editingId], [
                'name' => $validated['name'],
                'provider_id' => $validated['provider_id'],
                'category' => $validated['category'],
                'description' => $validated['description'] ?? null,
            ]);

            return $program->id;
        });

        $this->showModal = false;
        $this->resetPage();

        // A brand-new program has no form fields yet, so send the admin straight
        // into configuration instead of leaving it sitting unconfigured.
        if ($isNew) {
            $this->successMessage = null;
            $this->savedProgramId = $programId;
            $this->showSavedModal = true;

            return;
        }

        $this->successMessage = 'Scholarship program updated.';
    }

    public function toggleActive(int $id): void
    {
        $program = ScholarshipProgram::findOrFail($id);
        $program->update(['is_active' => ! $program->is_active]);
    }

    public function openProviderModal(): void
    {
        $this->reset(['newProviderName', 'newProviderFundingSource', 'newProviderContactPerson', 'newProviderContactEmail', 'newProviderAllocatedBudget']);
        $this->resetValidation();
        $this->showProviderModal = true;
    }

    public function saveNewProvider(): void
    {
        $validated = $this->validate([
            'newProviderName' => ['required', 'string', 'max:200'],
            'newProviderFundingSource' => ['required', new Enum(FundingSource::class)],
            'newProviderContactPerson' => ['nullable', 'string', 'max:150'],
            'newProviderContactEmail' => ['nullable', 'email', 'max:150'],
            'newProviderAllocatedBudget' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'newProviderName' => 'name',
            'newProviderFundingSource' => 'funding source',
            'newProviderContactPerson' => 'contact person',
            'newProviderContactEmail' => 'contact email',
            'newProviderAllocatedBudget' => 'allocated budget',
        ]);

        $provider = Provider::create([
            'name' => $validated['newProviderName'],
            'funding_source' => $validated['newProviderFundingSource'],
            'contact_person' => $validated['newProviderContactPerson'] ?: null,
            'contact_email' => $validated['newProviderContactEmail'] ?: null,
            'allocated_budget' => $validated['newProviderAllocatedBudget'] !== '' ? $validated['newProviderAllocatedBudget'] : null,
        ]);

        $this->provider_id = (string) $provider->id;
        $this->showProviderModal = false;
    }

    public function with(): array
    {
        return [
            'programs' => ScholarshipProgram::query()
                ->with('provider')
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderBy($this->sortBy, $this->sortDirection)
                ->paginate(10),
            'providers' => Provider::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <h1 class="font-display text-[30px] font-600 text-ink">Scholarship programs</h1>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 flex items-center justify-between gap-4">
        <div class="max-w-sm flex-1">
            <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by name" />
        </div>
        <x-ui.button wire:click="create">Add scholarship program</x-ui.button>
    </div>

    <div class="mt-4">
        @if ($programs->isEmpty())
            <x-ui.empty-state
                title="No scholarship programs yet"
                message="Add the grant's identity and requirements here first &mdash; you'll open term offerings for it afterward."
            >
                <x-slot:action>
                    <x-ui.button wire:click="create">Add scholarship program</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="[
                    ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                    ['key' => 'provider', 'label' => 'Provider', 'sortable' => false],
                    ['key' => 'funding_source', 'label' => 'Funding source', 'sortable' => false],
                    ['key' => 'category', 'label' => 'Type of scholarship', 'sortable' => false],
                    ['key' => 'is_active', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]"
                :rows="$programs"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
            >
                @foreach ($programs as $program)
                    {{--
                        The row opens the program's offerings. The name stays a real link
                        so the keyboard, middle-click and open-in-new-tab all still work;
                        the Alpine handler only widens the target to the whole row, and
                        skips navigating when the click ended a text selection.
                    --}}
                    <tr
                        wire:key="scholarship-program-{{ $program->id }}"
                        class="cursor-pointer transition hover:bg-paper"
                        x-on:click="if (! window.getSelection().toString()) window.location = '{{ route('admin.scholarship-programs.offerings', $program) }}'"
                    >
                        <td data-label="Name" class="text-[14px] text-ink">
                            <a
                                href="{{ route('admin.scholarship-programs.offerings', $program) }}"
                                class="rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2"
                            >{{ $program->name }}</a>
                        </td>
                        <td data-label="Provider" class="text-[14px] text-slate">{{ $program->provider->name }}</td>
                        <td data-label="Funding source" class="text-[14px] text-slate">{{ $program->provider->funding_source->label() }}</td>
                        <td data-label="Type of scholarship" class="text-[14px] text-slate">{{ $program->category->label() }}</td>
                        <td data-label="Status"><x-ui.active-stamp :active="$program->is_active" /></td>
                        <td data-label="Actions" class="space-x-3 text-[14px]" x-on:click.stop>
                            <a href="{{ route('admin.scholarship-programs.configure', $program) }}" class="text-forest-700 hover:underline">Requirements</a>
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

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit scholarship program' : 'Add scholarship program'" size="2xl">
        <form wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-ui.input name="name" label="Name" wire:model="name" required maxlength="200" />
                </div>

                <div class="space-y-1.5">
                    <x-ui.select name="provider_id" label="Provider" wire:model="provider_id" placeholder="Select a provider" required>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <button type="button" wire:click="openProviderModal" class="font-body text-[13px] font-500 text-forest-700 hover:underline">
                        + Add new provider
                    </button>
                </div>

                <x-ui.select name="category" label="Type of scholarship" wire:model="category" placeholder="Select a type" required>
                    @foreach (ScholarshipCategory::cases() as $categoryOption)
                        <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                    @endforeach
                </x-ui.select>

                <div class="md:col-span-2">
                    <x-ui.textarea name="description" label="Description" wire:model="description" rows="3">{{ $description }}</x-ui.textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save scholarship program</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal :show="$showSavedModal" title="Scholarship program added" size="md">
        <div class="space-y-5">
            <p class="font-body text-[14px] text-slate">
                Set up its application form fields and requirements before opening a term offering for it.
            </p>

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showSavedModal', false)">Not now</x-ui.button>
                @if ($savedProgramId)
                    <a href="{{ route('admin.scholarship-programs.configure', $savedProgramId) }}" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Set requirements</a>
                @endif
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal :show="$showProviderModal" title="Add provider" size="md">
        <form wire:submit="saveNewProvider" class="space-y-5">
            <x-ui.input name="newProviderName" label="Name" wire:model="newProviderName" required maxlength="200" />

            <x-ui.select name="newProviderFundingSource" label="Funding source" wire:model="newProviderFundingSource" placeholder="Select a funding source" required>
                @foreach (FundingSource::cases() as $fundingSourceOption)
                    <option value="{{ $fundingSourceOption->value }}">{{ $fundingSourceOption->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input name="newProviderContactPerson" label="Contact person" wire:model="newProviderContactPerson" maxlength="150" />
            <x-ui.input name="newProviderContactEmail" label="Contact email" type="email" wire:model="newProviderContactEmail" maxlength="150" />
            <x-ui.input name="newProviderAllocatedBudget" label="Allocated budget" type="number" step="0.01" min="0" wire:model="newProviderAllocatedBudget" />

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showProviderModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveNewProvider">Save provider</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
