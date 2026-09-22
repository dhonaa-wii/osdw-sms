<?php

use App\Enums\AllocationMode;
use App\Enums\CollegeScope;
use App\Models\AcademicTerm;
use App\Models\ProgramOffering;
use App\Models\Campus;
use App\Models\College;
use App\Models\ScholarshipProgram;
use App\Services\OfferingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Offerings'))] class extends Component
{
    public ScholarshipProgram $scholarshipProgram;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $academic_term_id = '';

    public string $name = '';

    public string $opens_at = '';

    public string $closes_at = '';

    public string $total_slots = '';

    public string $max_applications = '';

    public string $college_scope = 'all';

    public string $allocation_mode = 'open';

    public string $amount = '';

    public string $discount_rate = '';

    /** Ids of the colleges this offering is open to. */
    public array $offeringColleges = [];

    /** Slots per college, keyed by college id — quota allocation only. */
    public array $collegeSlots = [];

    public ?string $successMessage = null;

    public function mount(ScholarshipProgram $scholarshipProgram): void
    {
        $this->scholarshipProgram = $scholarshipProgram;

        // The offering page's Edit button links back here with the row to open,
        // so the offering form lives in one place only.
        if ($editId = request()->integer('edit')) {
            $this->edit($editId);
        }
    }

    protected function needsOfferingColleges(): bool
    {
        return $this->college_scope === CollegeScope::Restricted->value
            || $this->allocation_mode === AllocationMode::Quota->value;
    }

    public function isQuota(): bool
    {
        return $this->allocation_mode === AllocationMode::Quota->value;
    }

    public function create(): void
    {
        $this->reset([
            'editingId', 'academic_term_id', 'name', 'opens_at', 'closes_at',
            'total_slots', 'max_applications', 'amount', 'discount_rate',
            'college_scope', 'allocation_mode', 'offeringColleges', 'collegeSlots',
        ]);

        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $offering = ProgramOffering::with('offeringColleges')
            ->where('scholarship_program_id', $this->scholarshipProgram->id)
            ->findOrFail($id);

        $this->editingId = $offering->id;
        $this->academic_term_id = (string) $offering->academic_term_id;
        $this->name = (string) $offering->name;
        $this->opens_at = $offering->opens_at->format('Y-m-d\TH:i');
        $this->closes_at = $offering->closes_at->format('Y-m-d\TH:i');
        $this->total_slots = (string) $offering->total_slots;
        $this->max_applications = $offering->max_applications === null ? '' : (string) $offering->max_applications;
        $this->college_scope = $offering->college_scope->value;
        $this->allocation_mode = $offering->allocation_mode->value;
        $this->amount = $offering->amount === null ? '' : (string) $offering->amount;
        $this->discount_rate = $offering->discount_rate === null ? '' : (string) $offering->discount_rate;
        $this->offeringColleges = $offering->offeringColleges
            ->pluck('college_id')
            ->map(fn ($collegeId) => (string) $collegeId)
            ->all();

        $this->collegeSlots = $offering->offeringColleges
            ->mapWithKeys(fn ($offeringCollege) => [
                $offeringCollege->college_id => $offeringCollege->slots === null ? '' : (string) $offeringCollege->slots,
            ])->all();

        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    protected function rules(): array
    {
        $rules = [
            'academic_term_id' => ['required', 'exists:academic_terms,id'],
            'name' => ['required', 'string', 'max:150'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'total_slots' => ['required', 'integer', 'min:1'],
            'max_applications' => ['nullable', 'integer', 'min:1'],
            'college_scope' => ['required', 'in:'.implode(',', array_column(CollegeScope::cases(), 'value'))],
            'allocation_mode' => ['required', 'in:'.implode(',', array_column(AllocationMode::cases(), 'value'))],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];

        if ($this->needsOfferingColleges()) {
            $rules['offeringColleges'] = ['required', 'array', 'min:1'];
            $rules['offeringColleges.*'] = ['distinct', 'exists:colleges,id'];

            // A quota splits the total between the colleges chosen, so each
            // one ticked carries its own number.
            if ($this->isQuota()) {
                foreach ($this->offeringColleges as $collegeId) {
                    $rules['collegeSlots.'.$collegeId] = ['required', 'integer', 'min:1'];
                }
            }
        }

        return $rules;
    }

    /**
     * Keeps the per-college slot errors readable — without this they read
     * "the college slots.4 field is required".
     */
    protected function validationAttributes(): array
    {
        $attributes = ['offeringColleges' => 'eligible colleges'];

        foreach ($this->offeringColleges as $collegeId) {
            $attributes['collegeSlots.'.$collegeId] = 'slots';
        }

        return $attributes;
    }

    public function save(OfferingService $offeringService): void
    {
        $campus = Campus::first();

        if (! $campus) {
            $this->addError('campus', 'Set up the campus in Application Settings before opening an offering.');

            return;
        }

        $validated = $this->validate();
        $needsOfferingColleges = $this->needsOfferingColleges();
        $isQuota = $this->isQuota();

        $attributes = [
            'academic_term_id' => $validated['academic_term_id'],
            'name' => $validated['name'],
            'campus_id' => $campus->id,
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'total_slots' => $validated['total_slots'],
            'max_applications' => $validated['max_applications'] !== null && $validated['max_applications'] !== '' ? $validated['max_applications'] : null,
            'college_scope' => $validated['college_scope'],
            'allocation_mode' => $validated['allocation_mode'],
            'amount' => $validated['amount'] !== null && $validated['amount'] !== '' ? $validated['amount'] : null,
            'discount_rate' => $validated['discount_rate'] !== null && $validated['discount_rate'] !== '' ? $validated['discount_rate'] : null,
        ];

        $isNew = $this->editingId === null;

        DB::transaction(function () use ($offeringService, $attributes, $needsOfferingColleges, $isQuota, $validated): void {
            if ($this->editingId === null) {
                $offering = $offeringService->openOffering($this->scholarshipProgram, $attributes);
            } else {
                $offering = ProgramOffering::where('scholarship_program_id', $this->scholarshipProgram->id)
                    ->findOrFail($this->editingId);

                $offering->update($attributes);
            }

            $offering->offeringColleges()->delete();

            if ($needsOfferingColleges) {
                foreach ($validated['offeringColleges'] as $collegeId) {
                    $offering->offeringColleges()->create([
                        'college_id' => $collegeId,
                        'slots' => $isQuota ? data_get($validated, 'collegeSlots.'.$collegeId) : null,
                    ]);
                }
            }
        });

        $this->showForm = false;
        $this->successMessage = $isNew ? 'Offering added.' : 'Offering updated.';
    }

    public function with(): array
    {
        return [
            'offerings' => ProgramOffering::where('scholarship_program_id', $this->scholarshipProgram->id)
                ->withCount('applications')
                ->with('academicTerm')
                ->orderByDesc('opens_at')
                ->get(),
            'academicTerms' => AcademicTerm::orderByDesc('start_date')->get(),
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
            'hasCampus' => Campus::exists(),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.scholarship-programs')" label="Scholarship Programs" />

    <div class="mt-3 flex items-center justify-between">
        <div>
            <h1 class="font-display text-[30px] font-600 text-ink">{{ $scholarshipProgram->name }} offerings</h1>
        </div>
        @unless ($showForm)
            <div class="flex flex-wrap gap-3">
                <x-ui.button-link variant="secondary" :href="route('admin.scholarship-programs.configure', $scholarshipProgram)">Requirements</x-ui.button-link>
                <x-ui.button wire:click="create">Add offering</x-ui.button>
            </div>
        @endunless
    </div>

    @if ($successMessage && ! $showForm)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    @if (! $hasCampus)
        <x-ui.alert type="warning" class="mt-6">
            No campus is configured yet. <a href="{{ route('admin.campus') }}" class="underline">Set it up in Application Settings</a> before opening an offering.
        </x-ui.alert>
    @endif

    @error('campus')
        <x-ui.alert type="danger" class="mt-6">{{ $message }}</x-ui.alert>
    @enderror

    @if (! $showForm)
        <div class="mt-6">
            @if ($offerings->isEmpty())
                <x-ui.empty-state
                    title="No offerings yet"
                    message="Open this grant for a term by adding its first offering &mdash; slots and dates."
                >
                    <x-slot:action>
                        <x-ui.button wire:click="create">Add offering</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <x-ui.data-table
                    :headers="[
                        ['key' => 'offering', 'label' => 'Offering', 'sortable' => false],
                        ['key' => 'window', 'label' => 'Application window', 'sortable' => false],
                        ['key' => 'slots', 'label' => 'Slots', 'sortable' => false],
                        ['key' => 'allocation', 'label' => 'Allocation', 'sortable' => false],
                        ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                        ['key' => 'actions', 'label' => '', 'sortable' => false],
                    ]"
                    :rows="$offerings"
                >
                    @foreach ($offerings as $offering)
                        <tr
                            wire:key="offering-{{ $offering->id }}"
                            class="cursor-pointer transition hover:bg-paper"
                            x-on:click="if (! window.getSelection().toString()) window.location = '{{ route('admin.offerings.view', $offering) }}'"
                        >
                            <td data-label="Offering" class="text-[14px] text-ink">
                                <a
                                    href="{{ route('admin.offerings.view', $offering) }}"
                                    class="rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2"
                                >{{ $offering->displayName() }}</a>
                                <p class="font-body text-[12px] text-mist">{{ $offering->academicTerm->school_year }} &middot; {{ $offering->academicTerm->semester->label() }}</p>
                            </td>
                            <td data-label="Application window" class="font-mono text-[13px] text-slate">
                                {{ $offering->opens_at->format('M j, Y') }} &ndash; {{ $offering->closes_at->format('M j, Y') }}
                            </td>
                            <td data-label="Slots" class="font-mono text-[14px] text-ink">
                                {{ $offering->total_slots }}
                                @if ($offering->max_applications)
                                    <span class="text-[12px] text-mist">({{ $offering->applications_count }}/{{ $offering->max_applications }} applied)</span>
                                @endif
                            </td>
                            <td data-label="Allocation" class="text-[14px] text-slate">{{ $offering->allocation_mode->label() }}</td>
                            <td data-label="Status"><x-ui.status-stamp :status="$offering->registrationStatus()" /></td>
                            <td data-label="Actions" class="space-x-3 text-[14px]" x-on:click.stop>
                                <a href="{{ route('admin.offerings.configure', $offering) }}" class="text-forest-700 hover:underline">Requirements</a>
                                <button type="button" wire:click="edit({{ $offering->id }})" class="text-forest-700 hover:underline">Edit</button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </div>
    @else
        <form wire:submit="save" class="mt-8 max-w-3xl space-y-8">
            <div>
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Term &amp; window</p>
                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="name" label="Offering name" wire:model="name" required maxlength="150" placeholder="Batch 1" />

                    <x-ui.select name="academic_term_id" label="Academic term" wire:model="academic_term_id" placeholder="Select a term" required>
                        @foreach ($academicTerms as $term)
                            <option value="{{ $term->id }}">{{ $term->school_year }} &middot; {{ $term->semester->label() }}{{ $term->is_current ? ' (current)' : '' }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input name="total_slots" label="Total slots" type="number" min="1" wire:model="total_slots" required />

                    <x-ui.input name="max_applications" label="Maximum applications" type="number" min="1" wire:model="max_applications" hint="Leave blank for no cap." />

                    <x-ui.input name="opens_at" label="Opens" type="datetime-local" wire:model="opens_at" required />
                    <x-ui.input name="closes_at" label="Closes" type="datetime-local" wire:model="closes_at" required />
                </div>
            </div>

            <div class="border-t border-line pt-6">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Eligibility &amp; allocation</p>
                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="college_scope" label="College scope" wire:model.live="college_scope" required>
                        @foreach (\App\Enums\CollegeScope::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="allocation_mode" label="Allocation mode" wire:model.live="allocation_mode" required>
                        @foreach (\App\Enums\AllocationMode::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                @if ($this->needsOfferingColleges())
                    <fieldset class="mt-5">
                        <legend class="font-body text-[13px] font-500 tracking-[0.02em] text-ink">
                            Eligible colleges <span class="text-crimson-600">*</span>
                        </legend>

                        @if ($colleges->isEmpty())
                            <p class="mt-1 font-body text-[13px] text-slate">
                                No colleges are set up yet.
                                <a href="{{ route('admin.colleges') }}" class="font-500 text-forest-700 underline">Add them in Colleges</a>
                                before restricting this offering.
                            </p>
                        @else
                            @if ($this->isQuota())
                                <p class="mt-1 font-body text-[13px] text-mist">Slots must add up to the total above.</p>
                            @endif

                            <div class="mt-2 grid grid-cols-1 gap-2 rounded-card border border-line bg-paper p-4 {{ $this->isQuota() ? '' : 'sm:grid-cols-2' }}">
                                @foreach ($colleges as $college)
                                    <div class="flex items-center justify-between gap-3" wire:key="offering-college-{{ $college->id }}">
                                        <label class="flex items-center gap-2 font-body text-[13px] text-ink">
                                            <input
                                                type="checkbox"
                                                value="{{ $college->id }}"
                                                wire:model.live="offeringColleges"
                                                class="rounded border-line text-forest-600 focus-visible:ring-forest-600"
                                            >
                                            {{ $college->name }}
                                        </label>

                                        @if ($this->isQuota() && in_array($college->id, $offeringColleges))
                                            <div class="w-28 shrink-0">
                                                <x-ui.input
                                                    compact
                                                    name="collegeSlots.{{ $college->id }}"
                                                    label="Slots"
                                                    type="number"
                                                    min="1"
                                                    wire:model="collegeSlots.{{ $college->id }}"
                                                />
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('offeringColleges')
                            <p class="mt-1.5 font-body text-[13px] text-crimson-600">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endif
            </div>

            <div class="border-t border-line pt-6">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Award terms</p>
                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="amount" label="Cash grant amount" type="number" step="0.01" min="0" wire:model="amount" hint="Leave blank for a tuition discount instead." />
                    <x-ui.input name="discount_rate" label="Tuition discount (%)" type="number" step="0.01" min="0" max="100" wire:model="discount_rate" hint="Leave blank for a cash grant instead." />
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-line pt-6">
                <x-ui.button variant="secondary" type="button" wire:click="cancel">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save offering</x-ui.button>
            </div>
        </form>
    @endif
</div>
