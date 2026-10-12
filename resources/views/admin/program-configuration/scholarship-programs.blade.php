<?php

use App\Enums\FundingSource;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Services\DeletionService;
use App\Services\StaffNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Scholarship Programs'))] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $provider = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    public string $sortDirection = 'asc';

    public ?int $editingId = null;

    public string $name = '';

    public string $provider_id = '';

    public string $description = '';

    public string $newProviderName = '';

    public string $newProviderFundingSource = '';

    public string $newProviderContactPerson = '';

    public string $newProviderContactEmail = '';

    public string $newProviderAllocatedBudget = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'provider', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function sort(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    }

    /** Called when a form modal opens, so errors from the last attempt don't carry over. */
    public function clearErrors(): void
    {
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:200'],
            'provider_id' => ['required', 'exists:providers,id'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => 'Enter the scholarship program name.',
            'provider_id.required' => 'Choose the provider, or add a new one.',
        ]);

        $program = ScholarshipProgram::updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'provider_id' => $validated['provider_id'],
            'description' => $validated['description'] ?: null,
        ]);

        if ($program->wasRecentlyCreated) {
            app(StaffNotificationService::class)->programCreated($program);
        }

        $this->dispatch('program-saved');
        $this->dispatch(
            'notice',
            title: $this->editingId ? 'Scholarship program updated' : 'Scholarship program added',
            message: $program->name,
        );
    }

    public function toggleActive(int $id): void
    {
        $program = ScholarshipProgram::findOrFail($id);
        $program->update(['is_active' => ! $program->is_active]);
    }

    public function deleteProgram(int $id, DeletionService $deletionService): void
    {
        $program = ScholarshipProgram::findOrFail($id);

        try {
            $deletionService->deleteProgram($program);
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Scholarship program not deleted', message: $exception->getMessage());

            return;
        }

        $this->dispatch(
            'notice',
            title: 'Scholarship program deleted',
            message: 'Restore it from Settings → Deleted items within '.DeletionService::RETENTION_DAYS.' days.',
        );
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
        $this->dispatch('provider-saved');
    }

    public function with(): array
    {
        $filtered = ScholarshipProgram::query()
            ->when($this->search, fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->provider, fn (Builder $query) => $query->where('provider_id', $this->provider));

        return [
            'programs' => (clone $filtered)
                ->with('provider')
                ->withCount('offerings')
                ->when($this->status !== 'all', fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
                ->orderBy('name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
                ->paginate(10),
            'counts' => [
                'all' => (clone $filtered)->count(),
                'active' => (clone $filtered)->where('is_active', true)->count(),
                'inactive' => (clone $filtered)->where('is_active', false)->count(),
            ],
            'providers' => Provider::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div
    x-data="{
        formOpen: false,
        providerOpen: false,
        confirming: null,
        openForm(program = null) {
            $wire.editingId = program?.id ?? null;
            $wire.name = program?.name ?? '';
            $wire.provider_id = program?.provider_id ?? '';
            $wire.description = program?.description ?? '';
            this.formOpen = true;
            $wire.clearErrors();
        },
        openProvider() {
            $wire.newProviderName = '';
            $wire.newProviderFundingSource = '';
            $wire.newProviderContactPerson = '';
            $wire.newProviderContactEmail = '';
            $wire.newProviderAllocatedBudget = '';
            this.providerOpen = true;
            $wire.clearErrors();
        },
        async deactivate() {
            await $wire.toggleActive(this.confirming.id);
            this.confirming = null;
        },
        closeTop() {
            if (this.providerOpen) { this.providerOpen = false; return; }
            this.formOpen = false;
            this.confirming = null;
        },
    }"
    x-init="
        $wire.$on('program-saved', () => formOpen = false);
        $wire.$on('provider-saved', () => providerOpen = false);
    "
    @keydown.escape.window="closeTop()"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Scholarship programs</h1>
        <div class="flex flex-wrap gap-2">
            <x-ui.button-link variant="secondary" :href="route('admin.scholarship-programs.standard-requirements')" wire:navigate>
                <x-ui.icon name="clipboard-check" class="size-4" />
                Standard requirements
            </x-ui.button-link>
            <x-ui.button x-on:click="openForm()">
                <x-ui.icon name="plus" class="size-4" />
                Add scholarship program
            </x-ui.button>
        </div>
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search scholarship programs"
                placeholder="Search by name"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <div class="relative md:w-60">
                <label for="provider" class="sr-only">Filter by provider</label>
                <x-ui.icon name="building" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                <select
                    id="provider"
                    wire:model.live="provider"
                    class="block h-10 w-full appearance-none truncate rounded-control border border-line bg-surface pl-9 pr-9 font-body text-[14px] text-ink transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25"
                >
                    <option value="">All providers</option>
                    @foreach ($providers as $providerOption)
                        <option value="{{ $providerOption->id }}">{{ $providerOption->name }}</option>
                    @endforeach
                </select>
                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
            </div>

            <div class="flex rounded-control bg-paper p-1 md:ml-auto" role="group" aria-label="Filter by status">
                @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('status', '{{ $value }}')"
                        aria-pressed="{{ $status === $value ? 'true' : 'false' }}"
                        @class([
                            'flex flex-1 items-center justify-center gap-1.5 rounded-[6px] px-3 py-1.5 font-body text-[13px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600',
                            'bg-surface text-ink shadow-sm' => $status === $value,
                            'text-slate hover:text-ink' => $status !== $value,
                        ])
                    >
                        {{ $label }}
                        <span class="font-mono text-[11px] text-mist">{{ $counts[$value] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="search, provider, status, sort, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($programs->isEmpty())
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                        <x-ui.icon name="award" class="size-6" />
                    </span>
                    @if ($search || $provider || $status !== 'all')
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No scholarship programs match</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Try another search or clear the filters.</p>
                        <x-ui.button variant="secondary" class="mt-4" wire:click="$set('search', ''); $set('provider', ''); $set('status', 'all')">Clear filters</x-ui.button>
                    @else
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No scholarship programs yet</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Add a program, then open its first offering.</p>
                        <x-ui.button class="mt-4" x-on:click="openForm()">
                            <x-ui.icon name="plus" class="size-4" />
                            Add scholarship program
                        </x-ui.button>
                    @endif
                </div>
            @else
                <table class="w-full
                    [&_thead]:hidden md:[&_thead]:table-header-group
                    [&_tbody_tr]:grid [&_tbody_tr]:grid-cols-[1fr_auto] [&_tbody_tr]:gap-x-3 [&_tbody_tr]:gap-y-1 [&_tbody_tr]:px-4 [&_tbody_tr]:py-3
                    md:[&_tbody_tr]:table-row md:[&_tbody_tr]:p-0
                    md:[&_td]:table-cell md:[&_td]:px-4 md:[&_td]:py-2.5">
                    <thead class="border-b border-line bg-paper/60">
                        <tr>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">
                                <button type="button" wire:click="sort" class="inline-flex items-center gap-1 transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                    Program
                                    <x-ui.icon name="arrow-{{ $sortDirection === 'desc' ? 'down' : 'up' }}" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Provider</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Offerings</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Status</th>
                            <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($programs as $program)
                            @php($offeringsUrl = route('admin.scholarship-programs.offerings', $program))
                            {{--
                                The whole row opens the program's offerings. The name stays a real
                                link so keyboard, middle-click and open-in-new-tab still work; the
                                row handler skips navigating when the click ended a text selection.
                            --}}
                            <tr
                                wire:key="scholarship-program-{{ $program->id }}"
                                class="group cursor-pointer transition-colors hover:bg-paper/60"
                                x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $offeringsUrl }}')"
                            >
                                <td class="col-start-1 min-w-0 md:max-w-0 md:w-[40%]">
                                    <a
                                        href="{{ $offeringsUrl }}"
                                        wire:navigate
                                        x-on:click.stop
                                        class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                    >{{ $program->name }}</a>
                                    @if ($program->description)
                                        <p class="truncate font-body text-[12px] text-mist" title="{{ $program->description }}">{{ $program->description }}</p>
                                    @endif
                                </td>
                                <td class="col-start-1 min-w-0 font-body text-[13px]">
                                    <p class="truncate text-slate">{{ $program->provider->name }}</p>
                                    <p class="text-[12px] text-mist">{{ $program->provider->funding_source->label() }}</p>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate">
                                    <span class="font-mono text-ink">{{ $program->offerings_count }}</span>
                                    <span class="md:hidden">{{ Str::plural('offering', $program->offerings_count) }}</span>
                                </td>
                                <td class="col-start-1">
                                    <x-ui.active-stamp :active="$program->is_active" />
                                </td>
                                <td class="col-start-2 row-span-4 row-start-1 self-center" x-on:click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="openForm(@js(['id' => $program->id, 'name' => $program->name, 'provider_id' => (string) $program->provider_id, 'description' => (string) $program->description]))"
                                            class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                            title="Edit scholarship program"
                                            aria-label="Edit {{ $program->name }}"
                                        >
                                            <x-ui.icon name="pencil" class="size-4" />
                                        </button>
                                        @if ($program->is_active)
                                            <button
                                                type="button"
                                                @click="confirming = @js(['id' => $program->id, 'name' => $program->name])"
                                                class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-crimson-600/10 hover:text-crimson-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                                title="Deactivate scholarship program"
                                                aria-label="Deactivate {{ $program->name }}"
                                            >
                                                <x-ui.icon name="power" class="size-4" />
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="toggleActive({{ $program->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="toggleActive({{ $program->id }})"
                                                class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:opacity-60"
                                                title="Activate scholarship program"
                                                aria-label="Activate {{ $program->name }}"
                                            >
                                                <x-ui.icon name="power" class="size-4" wire:loading.remove wire:target="toggleActive({{ $program->id }})" />
                                                <x-ui.spinner class="size-4" wire:loading wire:target="toggleActive({{ $program->id }})" />
                                            </button>
                                        @endif
                                        <x-ui.icon-button
                                            icon="trash"
                                            tone="danger"
                                            label="Delete {{ $program->name }}"
                                            loading="deleteProgram({{ $program->id }})"
                                            x-on:click="$store.confirm.ask({
                                                title: {{ Js::from('Delete '.$program->name.'?') }},
                                                message: {{ Js::from('Its offerings are deleted with it. You can restore them from Settings → Deleted items within '.\App\Services\DeletionService::RETENTION_DAYS.' days.') }},
                                                confirmLabel: 'Delete scholarship program',
                                                action: () => $wire.deleteProgram({{ $program->id }}),
                                            })"
                                        />
                                        <a
                                            href="{{ $offeringsUrl }}"
                                            wire:navigate
                                            class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                            title="Open offerings"
                                            aria-label="Open offerings for {{ $program->name }}"
                                        >
                                            <x-ui.icon name="chevron-right" class="size-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($programs->hasPages())
            <div class="border-t border-line px-4 py-3">
                {{ $programs->links() }}
            </div>
        @endif
    </div>

    {{-- Add / edit form. Visibility lives in Alpine so it opens instantly and survives re-renders. --}}
    <div
        x-show="formOpen"
        x-cloak
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4 py-8"
        data-modal-backdrop
        @click.self="formOpen = false"
    >
        <div
            x-show="formOpen"
            x-trap.noscroll="formOpen && ! providerOpen"
            class="flex max-h-full w-full max-w-lg flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10"
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="scholarship-program-form-title"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line py-2.5 pl-4 pr-2.5">
                <h2 id="scholarship-program-form-title" class="font-display text-[15px] font-600 text-ink" x-text="$wire.editingId ? 'Edit scholarship program' : 'Add scholarship program'"></h2>
                <button type="button" @click="formOpen = false" class="flex size-7 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Close">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>

            <form wire:submit="save" class="min-h-0 space-y-3.5 overflow-y-auto px-4 py-3.5">
                <x-ui.input name="name" label="Name" wire:model="name" required maxlength="200" />

                <div>
                    <div class="flex items-end justify-between gap-3">
                        <label for="provider_id" class="block font-body text-[13px] font-500 text-ink">
                            Provider <span class="text-crimson-600">*</span>
                        </label>
                        <button type="button" @click="openProvider()" class="inline-flex items-center gap-1 rounded-sm font-body text-[12px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                            <x-ui.icon name="plus" class="size-3.5" />
                            New provider
                        </button>
                    </div>
                    <x-ui.select name="provider_id" wire:model="provider_id" placeholder="Select a provider" required>
                        @foreach ($providers as $providerOption)
                            <option value="{{ $providerOption->id }}">{{ $providerOption->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.textarea name="description" label="Description" wire:model="description" rows="3" />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="formOpen = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" loading="save">Save scholarship program</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    {{-- New provider, stacked over the program form --}}
    <div
        x-show="providerOpen"
        x-cloak
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[55] flex items-center justify-center bg-ink/40 px-4 py-8"
        data-modal-backdrop
        @click.self="providerOpen = false"
    >
        <div
            x-show="providerOpen"
            x-trap="providerOpen"
            class="flex max-h-full w-full max-w-md flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10"
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="provider-form-title"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line py-2.5 pl-4 pr-2.5">
                <h2 id="provider-form-title" class="font-display text-[15px] font-600 text-ink">Add provider</h2>
                <button type="button" @click="providerOpen = false" class="flex size-7 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Close">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>

            <form wire:submit="saveNewProvider" class="min-h-0 space-y-3.5 overflow-y-auto px-4 py-3.5">
                <x-ui.input name="newProviderName" label="Name" wire:model="newProviderName" required maxlength="200" />

                <x-ui.select name="newProviderFundingSource" label="Funding source" wire:model="newProviderFundingSource" placeholder="Select a funding source" required>
                    @foreach (FundingSource::cases() as $fundingSourceOption)
                        <option value="{{ $fundingSourceOption->value }}">{{ $fundingSourceOption->label() }}</option>
                    @endforeach
                </x-ui.select>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="newProviderContactPerson" label="Contact person" wire:model="newProviderContactPerson" maxlength="150" />
                    <x-ui.input name="newProviderContactEmail" label="Contact email" type="email" wire:model="newProviderContactEmail" maxlength="150" />
                </div>

                <x-ui.input name="newProviderAllocatedBudget" label="Allocated budget" type="number" step="0.01" min="0" wire:model="newProviderAllocatedBudget" class="font-mono" />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="providerOpen = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" loading="saveNewProvider">Save provider</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    {{-- Deactivate confirmation --}}
    <div
        x-show="confirming"
        x-cloak
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4 py-8"
        data-modal-backdrop
        @click.self="confirming = null"
    >
        <div
            x-show="confirming"
            x-trap.noscroll="confirming"
            class="w-full max-w-[340px] rounded-card border border-line bg-surface p-5 text-center shadow-xl shadow-ink/10"
            data-modal-panel
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="scholarship-program-confirm-title"
        >
            <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-crimson-600/10 text-crimson-600">
                <x-ui.icon name="power" class="size-6" />
            </span>
            <h2 id="scholarship-program-confirm-title" class="mt-3 font-display text-[15px] font-600 text-ink">
                Deactivate <span x-text="confirming?.name"></span>?
            </h2>
            <p class="mt-1 font-body text-[14px] text-slate">You can activate it again anytime.</p>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <x-ui.button variant="secondary" x-on:click="confirming = null">Cancel</x-ui.button>
                <x-ui.button variant="danger" loading="toggleActive" x-on:click="deactivate()">Deactivate</x-ui.button>
            </div>
        </div>
    </div>
</div>
