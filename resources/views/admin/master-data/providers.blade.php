<?php

use App\Enums\FundingSource;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Providers'))] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    public string $sortDirection = 'asc';

    public ?int $editingId = null;

    #[Validate('required|string|max:200', message: 'Enter the provider name.')]
    public string $name = '';

    #[Validate(['required', new Enum(FundingSource::class)], message: ['required' => 'Choose a funding source.'])]
    public string $funding_source = '';

    #[Validate('nullable|string|max:150')]
    public string $contact_person = '';

    #[Validate('nullable|email|max:150', message: ['email' => 'Enter a valid email address, e.g. name@agency.gov.ph.'])]
    public string $contact_email = '';

    #[Validate('nullable|numeric|min:0', message: ['numeric' => 'Enter the budget as a number, without ₱ or commas.'])]
    public string $allocated_budget = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'source', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function sort(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    }

    /** Called when the form modal opens, so errors from the last attempt don't carry over. */
    public function clearErrors(): void
    {
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $validated['contact_person'] = $validated['contact_person'] ?: null;
        $validated['contact_email'] = $validated['contact_email'] ?: null;
        $validated['allocated_budget'] = $validated['allocated_budget'] !== '' ? $validated['allocated_budget'] : null;

        $provider = Provider::updateOrCreate(['id' => $this->editingId], $validated);

        $this->dispatch('provider-saved');
        $this->dispatch(
            'notice',
            title: $this->editingId ? 'Provider updated' : 'Provider added',
            message: $provider->name,
        );
    }

    public function toggleActive(int $id): void
    {
        $provider = Provider::findOrFail($id);
        $provider->update(['is_active' => ! $provider->is_active]);
    }

    public function with(): array
    {
        $filtered = Provider::query()
            ->when($this->search, fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('contact_person', 'like', "%{$this->search}%")
            ))
            ->when($this->source, fn (Builder $query) => $query->where('funding_source', $this->source));

        return [
            'providers' => (clone $filtered)
                ->withCount('scholarshipPrograms')
                ->when($this->status !== 'all', fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
                ->orderBy('name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
                ->paginate(10),
            'counts' => [
                'all' => (clone $filtered)->count(),
                'active' => (clone $filtered)->where('is_active', true)->count(),
                'inactive' => (clone $filtered)->where('is_active', false)->count(),
            ],
        ];
    }
};
?>

<div
    x-data="{
        formOpen: false,
        confirming: null,
        openForm(provider = null) {
            $wire.editingId = provider?.id ?? null;
            $wire.name = provider?.name ?? '';
            $wire.funding_source = provider?.funding_source ?? '';
            $wire.contact_person = provider?.contact_person ?? '';
            $wire.contact_email = provider?.contact_email ?? '';
            $wire.allocated_budget = provider?.allocated_budget ?? '';
            this.formOpen = true;
            $wire.clearErrors();
        },
        async deactivate() {
            await $wire.toggleActive(this.confirming.id);
            this.confirming = null;
        },
    }"
    x-init="$wire.$on('provider-saved', () => formOpen = false)"
    @keydown.escape.window="formOpen = false; confirming = null"
>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Providers</h1>
        <x-ui.button x-on:click="openForm()">
            <x-ui.icon name="plus" class="size-4" />
            Add provider
        </x-ui.button>
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search providers"
                placeholder="Search by name or contact"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <div class="relative md:w-52">
                <label for="source" class="sr-only">Filter by funding source</label>
                <x-ui.icon name="layers" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                <select
                    id="source"
                    wire:model.live="source"
                    class="block h-10 w-full appearance-none rounded-control border border-line bg-surface pl-9 pr-9 font-body text-[14px] text-ink transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25"
                >
                    <option value="">All funding sources</option>
                    @foreach (FundingSource::cases() as $sourceOption)
                        <option value="{{ $sourceOption->value }}">{{ $sourceOption->label() }}</option>
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

        <div wire:loading.delay.class="opacity-60" wire:target="search, source, status, sort, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($providers->isEmpty())
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                        <x-ui.icon name="building" class="size-6" />
                    </span>
                    @if ($search || $source || $status !== 'all')
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No providers match</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Try another search or clear the filters.</p>
                        <x-ui.button variant="secondary" class="mt-4" wire:click="$set('search', ''); $set('source', ''); $set('status', 'all')">Clear filters</x-ui.button>
                    @else
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No providers yet</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Add the agencies and donors that fund scholarships.</p>
                        <x-ui.button class="mt-4" x-on:click="openForm()">
                            <x-ui.icon name="plus" class="size-4" />
                            Add provider
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
                                    Provider
                                    <x-ui.icon name="arrow-{{ $sortDirection === 'desc' ? 'down' : 'up' }}" class="size-3" />
                                </button>
                            </th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Contact</th>
                            <th class="px-4 py-2 text-right font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Budget</th>
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Status</th>
                            <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($providers as $provider)
                            @php($viewUrl = route('admin.providers.view', $provider))
                            {{--
                                The whole row opens the provider. The name stays a real link so
                                keyboard, middle-click and open-in-new-tab still work.
                            --}}
                            <tr
                                wire:key="provider-{{ $provider->id }}"
                                class="group cursor-pointer transition-colors hover:bg-paper/60"
                                x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $viewUrl }}')"
                            >
                                <td class="col-start-1 min-w-0 md:w-[34%] md:max-w-0">
                                    <a
                                        href="{{ $viewUrl }}"
                                        wire:navigate
                                        x-on:click.stop
                                        class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                    >{{ $provider->name }}</a>
                                    <p class="font-body text-[12px] text-mist">
                                        {{ $provider->funding_source->label() }} · {{ $provider->scholarship_programs_count }} {{ Str::plural('program', $provider->scholarship_programs_count) }}
                                    </p>
                                </td>
                                <td class="col-start-1 min-w-0 font-body text-[13px] md:max-w-0">
                                    @if ($provider->contact_person || $provider->contact_email)
                                        @if ($provider->contact_person)
                                            <p class="flex items-center gap-1.5 truncate text-slate">
                                                <x-ui.icon name="user" class="size-3.5 text-mist" />
                                                <span class="truncate">{{ $provider->contact_person }}</span>
                                            </p>
                                        @endif
                                        @if ($provider->contact_email)
                                            <p class="flex items-center gap-1.5 truncate text-[12px] text-mist">
                                                <x-ui.icon name="mail" class="size-3.5" />
                                                <span class="truncate">{{ $provider->contact_email }}</span>
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-mist">No contact on file</span>
                                    @endif
                                </td>
                                <td class="col-start-1 font-mono text-[13px] md:text-right">
                                    @if ($provider->allocated_budget !== null)
                                        <span class="text-ink">₱{{ number_format($provider->allocated_budget, 2) }}</span>
                                    @else
                                        <span class="text-mist">—</span>
                                    @endif
                                </td>
                                <td class="col-start-1">
                                    <x-ui.active-stamp :active="$provider->is_active" />
                                </td>
                                <td class="col-start-2 row-span-4 row-start-1 self-center" x-on:click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="openForm(@js(['id' => $provider->id, 'name' => $provider->name, 'funding_source' => $provider->funding_source->value, 'contact_person' => (string) $provider->contact_person, 'contact_email' => (string) $provider->contact_email, 'allocated_budget' => (string) $provider->allocated_budget]))"
                                            class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                            title="Edit provider"
                                            aria-label="Edit {{ $provider->name }}"
                                        >
                                            <x-ui.icon name="pencil" class="size-4" />
                                        </button>
                                        @if ($provider->is_active)
                                            <button
                                                type="button"
                                                @click="confirming = @js(['id' => $provider->id, 'name' => $provider->name])"
                                                class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-crimson-600/10 hover:text-crimson-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                                title="Deactivate provider"
                                                aria-label="Deactivate {{ $provider->name }}"
                                            >
                                                <x-ui.icon name="power" class="size-4" />
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="toggleActive({{ $provider->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="toggleActive({{ $provider->id }})"
                                                class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:opacity-60"
                                                title="Activate provider"
                                                aria-label="Activate {{ $provider->name }}"
                                            >
                                                <x-ui.icon name="power" class="size-4" wire:loading.remove wire:target="toggleActive({{ $provider->id }})" />
                                                <x-ui.spinner class="size-4" wire:loading wire:target="toggleActive({{ $provider->id }})" />
                                            </button>
                                        @endif
                                        <a
                                            href="{{ $viewUrl }}"
                                            wire:navigate
                                            class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                            title="View provider"
                                            aria-label="View {{ $provider->name }}"
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

        @if ($providers->hasPages())
            <div class="border-t border-line px-4 py-3">
                {{ $providers->links() }}
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
            x-trap.noscroll="formOpen"
            class="flex max-h-full w-full max-w-lg flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10"
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="provider-form-title"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line py-2.5 pl-4 pr-2.5">
                <h2 id="provider-form-title" class="font-display text-[15px] font-600 text-ink" x-text="$wire.editingId ? 'Edit provider' : 'Add provider'"></h2>
                <button type="button" @click="formOpen = false" class="flex size-7 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Close">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>

            <form wire:submit="save" class="min-h-0 space-y-3.5 overflow-y-auto px-4 py-3.5">
                <x-ui.input name="name" label="Name" wire:model="name" required maxlength="200" />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.select name="funding_source" label="Funding source" wire:model="funding_source" placeholder="Select a source" required>
                        @foreach (FundingSource::cases() as $fundingSourceOption)
                            <option value="{{ $fundingSourceOption->value }}">{{ $fundingSourceOption->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="allocated_budget" label="Allocated budget" type="number" step="0.01" min="0" wire:model="allocated_budget" placeholder="0.00" class="font-mono" hint="Leave blank for no budget cap" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="contact_person" label="Contact person" wire:model="contact_person" maxlength="150" />
                    <x-ui.input name="contact_email" label="Contact email" type="email" wire:model="contact_email" maxlength="150" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="formOpen = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" loading="save">Save provider</x-ui.button>
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
            aria-labelledby="provider-confirm-title"
        >
            <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-crimson-600/10 text-crimson-600">
                <x-ui.icon name="power" class="size-6" />
            </span>
            <h2 id="provider-confirm-title" class="mt-3 font-display text-[15px] font-600 text-ink">
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
