<?php

use App\Enums\DegreeLevel;
use App\Models\College;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Programs'))] class extends Component
{
    use WithPagination;

    private const SORTABLE = ['code', 'name'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $college = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public ?int $editingId = null;

    public string $college_id = '';

    public string $code = '';

    public string $name = '';

    public string $degree_level = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'college', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /** Called when the form modal opens, so errors from the last attempt don't carry over. */
    public function clearErrors(): void
    {
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'degree_level' => ['required', 'in:'.implode(',', array_column(DegreeLevel::cases(), 'value'))],
        ], [
            'college_id.required' => 'Choose the college that offers this program.',
            'code.required' => 'Enter the program code, e.g. BSIT.',
            'name.required' => 'Enter the full program name.',
            'degree_level.required' => 'Choose a degree level.',
        ]);

        $program = Program::updateOrCreate(['id' => $this->editingId], $validated);

        $this->dispatch('program-saved');
        $this->dispatch(
            'notice',
            title: $this->editingId ? 'Program updated' : 'Program added',
            message: "{$program->code} · {$program->name}",
        );
    }

    public function toggleActive(int $id): void
    {
        $program = Program::findOrFail($id);
        $program->update(['is_active' => ! $program->is_active]);
    }

    public function with(): array
    {
        $filtered = Program::query()
            ->when($this->search, fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%")
            ))
            ->when($this->college, fn (Builder $query) => $query->where('college_id', $this->college));

        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'name';

        return [
            'programs' => (clone $filtered)
                ->with('college')
                ->when($this->status !== 'all', fn (Builder $query) => $query->where('is_active', $this->status === 'active'))
                ->orderBy($sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
                ->paginate(10),
            'counts' => [
                'all' => (clone $filtered)->count(),
                'active' => (clone $filtered)->where('is_active', true)->count(),
                'inactive' => (clone $filtered)->where('is_active', false)->count(),
            ],
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div
    x-data="{
        formOpen: false,
        confirming: null,
        openForm(program = null) {
            $wire.editingId = program?.id ?? null;
            $wire.college_id = program?.college_id ?? '';
            $wire.code = program?.code ?? '';
            $wire.name = program?.name ?? '';
            $wire.degree_level = program?.degree_level ?? '';
            this.formOpen = true;
            $wire.clearErrors();
        },
        async deactivate() {
            await $wire.toggleActive(this.confirming.id);
            this.confirming = null;
        },
    }"
    x-init="$wire.$on('program-saved', () => formOpen = false)"
    @keydown.escape.window="formOpen = false; confirming = null"
>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Programs</h1>
        <x-ui.button x-on:click="openForm()">
            <x-ui.icon name="plus" class="size-4" />
            Add program
        </x-ui.button>
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search programs"
                placeholder="Search by code or name"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <div class="relative md:w-56">
                <label for="college" class="sr-only">Filter by college</label>
                <x-ui.icon name="building" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-mist" />
                <select
                    id="college"
                    wire:model.live="college"
                    class="block h-10 w-full appearance-none rounded-control border border-line bg-surface pl-9 pr-9 font-body text-[14px] text-ink transition-colors hover:border-navy-100 focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25"
                >
                    <option value="">All colleges</option>
                    @foreach ($colleges as $collegeOption)
                        <option value="{{ $collegeOption->id }}">{{ $collegeOption->code }} · {{ $collegeOption->name }}</option>
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

        <div wire:loading.delay.class="opacity-60" wire:target="search, college, status, sort, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($programs->isEmpty())
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                        <x-ui.icon name="graduation" class="size-6" />
                    </span>
                    @if ($search || $college || $status !== 'all')
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No programs match</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Try another search or clear the filters.</p>
                        <x-ui.button variant="secondary" class="mt-4" wire:click="$set('search', ''); $set('college', ''); $set('status', 'all')">Clear filters</x-ui.button>
                    @else
                        <p class="mt-3 font-display text-[16px] font-600 text-ink">No programs yet</p>
                        <p class="mt-1 font-body text-[14px] text-slate">Add the programs students enroll in.</p>
                        <x-ui.button class="mt-4" x-on:click="openForm()">
                            <x-ui.icon name="plus" class="size-4" />
                            Add program
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
                            @foreach ([['code', 'Program'], ['college', 'College'], ['level', 'Level'], ['status', 'Status']] as [$key, $label])
                                <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">
                                    @if ($key === 'code')
                                        <button type="button" wire:click="sort('name')" class="inline-flex items-center gap-1 transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                            {{ $label }}
                                            <x-ui.icon name="arrow-{{ $sortDirection === 'desc' ? 'down' : 'up' }}" class="size-3" />
                                        </button>
                                    @else
                                        {{ $label }}
                                    @endif
                                </th>
                            @endforeach
                            <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($programs as $program)
                            <tr wire:key="program-{{ $program->id }}" class="transition-colors hover:bg-paper/60">
                                <td class="col-start-1 min-w-0">
                                    <p class="truncate font-body text-[14px] font-500 text-ink" title="{{ $program->name }}">{{ $program->name }}</p>
                                    <p class="font-mono text-[12px] text-slate">{{ $program->code }}</p>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate" title="{{ $program->college->name }}">
                                    <span class="md:hidden">{{ $program->college->code }} · {{ $program->degree_level->label() }}</span>
                                    <span class="hidden md:inline">{{ $program->college->code }}</span>
                                </td>
                                <td class="hidden font-body text-[13px] text-slate md:table-cell">{{ $program->degree_level->label() }}</td>
                                <td class="col-start-1">
                                    <x-ui.active-stamp :active="$program->is_active" />
                                </td>
                                <td class="col-start-2 row-span-3 row-start-1 self-center">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="openForm(@js(['id' => $program->id, 'college_id' => (string) $program->college_id, 'code' => $program->code, 'name' => $program->name, 'degree_level' => $program->degree_level->value]))"
                                            class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                            title="Edit program"
                                            aria-label="Edit {{ $program->code }}"
                                        >
                                            <x-ui.icon name="pencil" class="size-4" />
                                        </button>
                                        @if ($program->is_active)
                                            <button
                                                type="button"
                                                @click="confirming = @js(['id' => $program->id, 'code' => $program->code])"
                                                class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-crimson-600/10 hover:text-crimson-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                                title="Deactivate program"
                                                aria-label="Deactivate {{ $program->code }}"
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
                                                title="Activate program"
                                                aria-label="Activate {{ $program->code }}"
                                            >
                                                <x-ui.icon name="power" class="size-4" wire:loading.remove wire:target="toggleActive({{ $program->id }})" />
                                                <x-ui.spinner class="size-4" wire:loading wire:target="toggleActive({{ $program->id }})" />
                                            </button>
                                        @endif
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
            x-trap.noscroll="formOpen"
            class="flex max-h-full w-full max-w-md flex-col rounded-card border border-line bg-surface shadow-xl shadow-ink/10"
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="program-form-title"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line py-2.5 pl-4 pr-2.5">
                <h2 id="program-form-title" class="font-display text-[15px] font-600 text-ink" x-text="$wire.editingId ? 'Edit program' : 'Add program'"></h2>
                <button type="button" @click="formOpen = false" class="flex size-7 items-center justify-center rounded-control text-mist transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-label="Close">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>

            <form wire:submit="save" class="min-h-0 space-y-3.5 overflow-y-auto px-4 py-3.5">
                <x-ui.select name="college_id" label="College" wire:model="college_id" placeholder="Select a college" required>
                    @foreach ($colleges as $collegeOption)
                        <option value="{{ $collegeOption->id }}">{{ $collegeOption->name }}</option>
                    @endforeach
                </x-ui.select>

                <div class="grid grid-cols-[8rem_1fr] gap-3">
                    <x-ui.input name="code" label="Code" wire:model="code" required maxlength="30" class="font-mono" />
                    <x-ui.select name="degree_level" label="Degree level" wire:model="degree_level" placeholder="Select a level" required>
                        @foreach (DegreeLevel::cases() as $level)
                            <option value="{{ $level->value }}">{{ $level->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.input name="name" label="Name" wire:model="name" required maxlength="200" />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="formOpen = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" loading="save">Save program</x-ui.button>
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
            aria-labelledby="program-confirm-title"
        >
            <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-crimson-600/10 text-crimson-600">
                <x-ui.icon name="power" class="size-6" />
            </span>
            <h2 id="program-confirm-title" class="mt-3 font-display text-[15px] font-600 text-ink">
                Deactivate <span class="font-mono" x-text="confirming?.code"></span>?
            </h2>
            <p class="mt-1 font-body text-[14px] text-slate">You can activate it again anytime.</p>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <x-ui.button variant="secondary" x-on:click="confirming = null">Cancel</x-ui.button>
                <x-ui.button variant="danger" loading="toggleActive" x-on:click="deactivate()">Deactivate</x-ui.button>
            </div>
        </div>
    </div>
</div>
