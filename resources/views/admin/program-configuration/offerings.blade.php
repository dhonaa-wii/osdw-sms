<?php

use App\Enums\AllocationMode;
use App\Enums\CollegeScope;
use App\Services\RenewalService;
use App\Enums\ScholarshipCategory;
use App\Models\AcademicTerm;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use App\Services\DeletionService;
use App\Services\EligibilityService;
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

    public string $category = '';

    public string $opens_at = '';

    public string $closes_at = '';

    public string $total_slots = '';

    public string $max_applications = '';

    /** The highest family monthly income accepted — blank for no cap. */
    public string $max_monthly_income = '';

    public string $college_scope = 'all';

    public string $allocation_mode = 'open';

    public string $amount = '';

    /** The offering this one renews — set when it is added from a group. */
    public ?int $renews_offering_id = null;

    /** A renewal offering that also takes new applicants into its slots. */
    public bool $accepts_new_applicants = false;

    /** Ids of the colleges this offering is open to. */
    public array $offeringColleges = [];

    /** Slots per college, keyed by college id — quota allocation only. */
    public array $collegeSlots = [];

    public function mount(ScholarshipProgram $scholarshipProgram): void
    {
        $this->scholarshipProgram = $scholarshipProgram;

        // The offering page's Edit button links back here with the row to open,
        // so the offering form lives in one place only.
        if ($editId = request()->integer('edit')) {
            $this->edit($editId);
        } elseif ($renewsId = request()->integer('renews')) {
            // "Add renewal offering" on an offering's page links here with
            // the offering the new one continues.
            $this->createRenewal($renewsId);
        }
    }

    public function isRenewalForm(): bool
    {
        return $this->renews_offering_id !== null;
    }

    /**
     * Slots, cap, college scope and allocation apply only when the offering
     * takes new applicants.
     */
    public function takesNewApplicantsForm(): bool
    {
        return ! $this->isRenewalForm() || $this->accepts_new_applicants;
    }

    /**
     * Opening a renewal offering to new applicants starts its slots at the
     * number of scholars who were in their last term.
     */
    public function updatedAcceptsNewApplicants(bool $value): void
    {
        if ($value && $this->total_slots === '' && $this->renews_offering_id) {
            $suggested = app(RenewalService::class)->graduatingCount(ProgramOffering::findOrFail($this->renews_offering_id));
            $this->total_slots = $suggested > 0 ? (string) $suggested : '';
        }
    }

    protected function needsOfferingColleges(): bool
    {
        if (! $this->takesNewApplicantsForm()) {
            return false;
        }

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
            'editingId', 'academic_term_id', 'name', 'category', 'opens_at', 'closes_at',
            'total_slots', 'max_applications', 'max_monthly_income', 'amount', 'renews_offering_id', 'accepts_new_applicants',
            'college_scope', 'allocation_mode', 'offeringColleges', 'collegeSlots',
        ]);

        $this->resetValidation();
        $this->showForm = true;
    }

    /**
     * A new offering that continues an existing one. It keeps the source's
     * type and starts with its amount; its awardees renew into it.
     */
    public function createRenewal(int $sourceId): void
    {
        $source = ProgramOffering::where('scholarship_program_id', $this->scholarshipProgram->id)->findOrFail($sourceId);

        $this->create();
        $this->renews_offering_id = $source->id;
        $this->amount = (string) $source->amount;
    }

    public function edit(int $id): void
    {
        $offering = ProgramOffering::with('offeringColleges')
            ->where('scholarship_program_id', $this->scholarshipProgram->id)
            ->findOrFail($id);

        $this->editingId = $offering->id;
        $this->renews_offering_id = $offering->renews_offering_id;
        $this->accepts_new_applicants = $offering->accepts_new_applicants;
        $this->academic_term_id = (string) $offering->academic_term_id;
        $this->name = (string) $offering->name;
        $this->category = $offering->category->value;
        $this->opens_at = $offering->opens_at->format('Y-m-d\TH:i');
        $this->closes_at = $offering->closes_at->format('Y-m-d\TH:i');
        $this->total_slots = $offering->takesNewApplicants() ? (string) $offering->total_slots : '';
        $this->max_applications = $offering->max_applications === null ? '' : (string) $offering->max_applications;
        $this->max_monthly_income = (string) app(EligibilityService::class)->incomeCap($offering);
        $this->college_scope = $offering->college_scope->value;
        $this->allocation_mode = $offering->allocation_mode->value;
        $this->amount = (string) $offering->amount;
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
            'category' => ['required', 'in:'.implode(',', array_column(ScholarshipCategory::cases(), 'value'))],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'total_slots' => ['required', 'integer', 'min:1'],
            'max_applications' => ['nullable', 'integer', 'min:1'],
            'max_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'college_scope' => ['required', 'in:'.implode(',', array_column(CollegeScope::cases(), 'value'))],
            'allocation_mode' => ['required', 'in:'.implode(',', array_column(AllocationMode::cases(), 'value'))],
            'amount' => ['required', 'numeric', 'min:0'],
        ];

        // A renewal offering keeps the type of the offering it renews, and
        // has no slots, cap, college scope or allocation to set unless it
        // also takes new applicants.
        if ($this->isRenewalForm()) {
            unset($rules['category']);
            $rules['accepts_new_applicants'] = ['boolean'];
        }

        if (! $this->takesNewApplicantsForm()) {
            unset($rules['total_slots'], $rules['max_applications'], $rules['max_monthly_income'], $rules['college_scope'], $rules['allocation_mode']);
        }

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
        $attributes = ['offeringColleges' => 'eligible colleges', 'max_monthly_income' => 'maximum family monthly income'];

        if ($this->isRenewalForm()) {
            $attributes['opens_at'] = 'submission start';
            $attributes['closes_at'] = 'submission due date';
        }

        foreach ($this->offeringColleges as $collegeId) {
            $attributes['collegeSlots.'.$collegeId] = 'slots';
        }

        return $attributes;
    }

    public function save(OfferingService $offeringService, RenewalService $renewalService): void
    {
        $campus = Campus::first();

        if (! $campus) {
            $this->dispatch('notice', tone: 'error', title: 'No campus set up', message: 'Set up the campus in Application Settings before opening an offering.');

            return;
        }

        $validated = $this->validate();
        $needsOfferingColleges = $this->needsOfferingColleges();
        $isQuota = $this->isQuota();

        $attributes = [
            'academic_term_id' => $validated['academic_term_id'],
            'name' => $validated['name'],
            'category' => $this->isRenewalForm()
                ? ProgramOffering::findOrFail($this->renews_offering_id)->category->value
                : $validated['category'],
            'campus_id' => $campus->id,
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'amount' => $validated['amount'],
        ];

        if ($this->isRenewalForm()) {
            $attributes += [
                'renews_offering_id' => $this->renews_offering_id,
                'accepts_new_applicants' => $this->accepts_new_applicants,
            ];
        }

        $attributes += ! $this->takesNewApplicantsForm()
            ? $renewalService->renewalOfferingDefaults()
            : [
                'total_slots' => $validated['total_slots'],
                'max_applications' => $validated['max_applications'] !== null && $validated['max_applications'] !== '' ? $validated['max_applications'] : null,
                'college_scope' => $validated['college_scope'],
                'allocation_mode' => $validated['allocation_mode'],
            ];

        $isNew = $this->editingId === null;
        $offering = null;

        DB::transaction(function () use ($offeringService, $attributes, $needsOfferingColleges, $isQuota, $validated, &$offering): void {
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

            // Only an offering that takes new applicants screens their income.
            app(EligibilityService::class)->saveIncomeCap(
                $offering,
                $this->takesNewApplicantsForm() ? $validated['max_monthly_income'] : null,
            );
        });

        // A brand-new offering has no form fields, eligibility rules or
        // requirements yet — send staff straight there instead of back to
        // the list.
        if ($isNew) {
            $this->redirect(route('admin.offerings.configure', $offering), navigate: true);

            return;
        }

        $this->showForm = false;
        $this->dispatch('notice', title: 'Offering updated', message: $offering->displayName());
    }

    public function deleteOffering(int $offeringId, DeletionService $deletionService): void
    {
        $offering = ProgramOffering::where('scholarship_program_id', $this->scholarshipProgram->id)->findOrFail($offeringId);

        try {
            $deletionService->deleteOffering($offering);
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Offering not deleted', message: $exception->getMessage());

            return;
        }

        $this->dispatch(
            'notice',
            title: 'Offering deleted',
            message: 'Restore it from Settings → Deleted items within '.DeletionService::RETENTION_DAYS.' days.',
        );
    }

    public function with(RenewalService $renewalService): array
    {
        // One row per group: the first offering stands for every renewal
        // offering that continues it, which open as tabs on its page.
        $offerings = ProgramOffering::where('scholarship_program_id', $this->scholarshipProgram->id)
            ->whereNull('renews_offering_id')
            ->withCount(['applications' => fn ($query) => $query->where('kind', \App\Enums\ApplicationKind::New->value)])
            ->with('academicTerm')
            ->orderByDesc('opens_at')
            ->get()
            ->each(fn (ProgramOffering $offering) => $offering->setAttribute('renewal_offerings_count', $renewalService->chainOf($offering)->count() - 1));

        $renewsOffering = $this->renews_offering_id ? ProgramOffering::find($this->renews_offering_id) : null;

        return [
            'offerings' => $offerings,
            'renewsOffering' => $renewsOffering,
            'graduatingCount' => $renewsOffering && $this->showForm ? $renewalService->graduatingCount($renewsOffering) : 0,
            'academicTerms' => AcademicTerm::orderByDesc('start_date')->get(),
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
            'hasCampus' => Campus::exists(),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.scholarship-programs')" label="Scholarship Programs" />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">{{ $scholarshipProgram->name }} offerings</h1>
        <x-ui.button x-on:click="$wire.showForm = true; $wire.create()" :disabled="! $hasCampus">
            <x-ui.icon name="plus" class="size-4" />
            Add offering
        </x-ui.button>
    </div>

    @if (! $hasCampus)
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-card border border-line border-l-2 border-l-amber-600 bg-surface px-4 py-3 font-body text-[13px] text-ink">
            <x-ui.icon name="alert-circle" class="size-4 text-amber-600" />
            <span class="flex-1">Set up the campus before opening an offering.</span>
            <a href="{{ route('admin.campus') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Set up campus</a>
        </div>
    @endif

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <h2 class="font-display text-[15px] font-600 text-ink">Offerings</h2>
            <span class="font-mono text-[12px] text-mist">{{ $offerings->count() }}</span>
        </div>

        @if ($offerings->isEmpty())
            <div class="px-6 py-12 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-paper text-mist">
                    <x-ui.icon name="calendar" class="size-6" />
                </span>
                <p class="mt-3 font-display text-[16px] font-600 text-ink">No offerings yet</p>
                <p class="mt-1 font-body text-[14px] text-slate">Add the first offering to open this program for a term.</p>
                @if ($hasCampus)
                    <x-ui.button class="mt-4" x-on:click="$wire.showForm = true; $wire.create()">
                        <x-ui.icon name="plus" class="size-4" />
                        Add offering
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
                        @foreach (['Offering', 'Application window', 'Slots', 'Status'] as $heading)
                            <th class="px-4 py-2 text-left font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">{{ $heading }}</th>
                        @endforeach
                        <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($offerings as $offering)
                        @php($viewUrl = route('admin.offerings.view', $offering))
                        <tr
                            wire:key="offering-{{ $offering->id }}"
                            class="group cursor-pointer transition-colors hover:bg-paper/60"
                            x-on:click="if (! window.getSelection().toString()) Livewire.navigate('{{ $viewUrl }}')"
                        >
                            <td class="col-start-1 min-w-0">
                                <a
                                    href="{{ $viewUrl }}"
                                    wire:navigate
                                    x-on:click.stop
                                    class="block truncate rounded-sm font-body text-[14px] font-500 text-ink group-hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                >{{ $offering->displayName() }}</a>
                                <p class="font-body text-[12px] text-mist">
                                    {{ $offering->academicTerm->school_year }} · {{ $offering->academicTerm->semester->label() }} · {{ $offering->category->label() }}
                                </p>
                                @if ($offering->renewal_offerings_count > 0)
                                    <p class="mt-0.5 inline-flex items-center gap-1 font-body text-[12px] text-slate">
                                        <x-ui.icon name="layers" class="size-3.5 text-mist" />
                                        {{ $offering->renewal_offerings_count }} renewal {{ str('offering')->plural($offering->renewal_offerings_count) }}
                                    </p>
                                @endif
                            </td>
                            <td class="col-start-1 font-mono text-[12px] text-slate">
                                {{ $offering->opens_at->format('M j, Y') }} – {{ $offering->closes_at->format('M j, Y') }}
                            </td>
                            <td class="col-start-1 font-body text-[13px] text-slate">
                                <span class="font-mono text-ink">{{ $offering->total_slots }}</span>
                                <span class="md:hidden">slots</span>
                                <span class="text-[12px] text-mist">· {{ $offering->allocation_mode->label() }}</span>
                                @if ($offering->max_applications)
                                    <p class="text-[12px] text-mist"><span class="font-mono">{{ $offering->applications_count }}/{{ $offering->max_applications }}</span> applied</p>
                                @endif
                            </td>
                            <td class="col-start-1">
                                <x-ui.status-stamp :status="$offering->registrationStatus()" />
                            </td>
                            <td class="col-start-2 row-span-4 row-start-1 self-center" x-on:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <a
                                        href="{{ route('admin.offerings.configure', $offering) }}"
                                        wire:navigate
                                        class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                        title="Requirements"
                                        aria-label="Requirements for {{ $offering->displayName() }}"
                                    >
                                        <x-ui.icon name="clipboard-check" class="size-4" />
                                    </a>
                                    <button
                                        type="button"
                                        x-on:click="$wire.showForm = true; $wire.edit({{ $offering->id }})"
                                        class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                        title="Edit offering"
                                        aria-label="Edit {{ $offering->displayName() }}"
                                    >
                                        <x-ui.icon name="pencil" class="size-4" />
                                    </button>
                                    <x-ui.icon-button
                                        icon="trash"
                                        tone="danger"
                                        label="Delete {{ $offering->displayName() }}"
                                        loading="deleteOffering({{ $offering->id }})"
                                        x-on:click="$store.confirm.ask({
                                            title: {{ Js::from('Delete '.$offering->displayName().'?') }},
                                            message: {{ Js::from('You can restore it from Settings → Deleted items within '.\App\Services\DeletionService::RETENTION_DAYS.' days.') }},
                                            confirmLabel: 'Delete offering',
                                            action: () => $wire.deleteOffering({{ $offering->id }}),
                                        })"
                                    />
                                    <a
                                        href="{{ $viewUrl }}"
                                        wire:navigate
                                        class="flex size-8 items-center justify-center rounded-control text-slate transition-colors hover:bg-navy-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                        title="Open offering"
                                        aria-label="Open {{ $offering->displayName() }}"
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

    {{-- Add / edit offering --}}
    <x-ui.dialog
        show="$wire.showForm"
        close="$wire.showForm = false"
        size="2xl"
        loading="create, edit"
        :title="$editingId ? 'Edit offering' : ($this->isRenewalForm() ? 'Add renewal offering' : 'Add offering')"
    >
        <form wire:submit="save" class="space-y-5">
            @if ($renewsOffering)
                <div class="flex items-start gap-2 rounded-control bg-paper px-3 py-2.5 font-body text-[13px] text-slate">
                    <x-ui.icon name="info" class="mt-0.5 size-4 text-azure-600" />
                    <span>Renews scholars awarded in <span class="font-500 text-ink">{{ $renewsOffering->displayName() }}</span> ({{ $renewsOffering->academicTerm->school_year }} · {{ $renewsOffering->academicTerm->semester->label() }}).</span>
                </div>
            @endif

            <section class="space-y-3">
                <h3 class="flex items-center gap-2 font-body text-[12px] font-600 text-slate">
                    <x-ui.icon name="calendar" class="size-4 text-mist" />
                    {{ $this->isRenewalForm() ? 'Term and submission window' : 'Term and window' }}
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-ui.input name="name" label="Offering name" wire:model="name" required maxlength="150" :placeholder="$this->isRenewalForm() ? 'Renewal 1' : 'Batch 1'" />

                    @unless ($this->isRenewalForm())
                        <x-ui.select name="category" label="Type of scholarship" wire:model="category" placeholder="Select a type" required>
                            @foreach (\App\Enums\ScholarshipCategory::cases() as $categoryOption)
                                <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    @endunless

                    <x-ui.select name="academic_term_id" label="Academic term" wire:model="academic_term_id" placeholder="Select a term" required>
                        @foreach ($academicTerms as $term)
                            <option value="{{ $term->id }}">{{ $term->school_year }} · {{ $term->semester->label() }}{{ $term->is_current ? ' (current)' : '' }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input name="amount" label="Award amount (₱)" type="number" step="0.01" min="0" wire:model="amount" required class="font-mono" placeholder="0.00" />

                    @if ($this->isRenewalForm())
                        <x-ui.input name="opens_at" label="Submission starts" type="datetime-local" wire:model="opens_at" required />
                        <x-ui.input name="closes_at" label="Submission due" type="datetime-local" wire:model="closes_at" required />
                    @else
                        <x-ui.input name="opens_at" label="Opens" type="datetime-local" wire:model="opens_at" required />
                        <x-ui.input name="closes_at" label="Closes" type="datetime-local" wire:model="closes_at" required />
                    @endif
                </div>
            </section>

            @if ($this->isRenewalForm())
                <label class="flex cursor-pointer items-start justify-between gap-4 rounded-control border border-line px-3 py-2.5 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-navy-600">
                    <span>
                        <span class="block font-body text-[14px] font-500 text-ink">Open to new applicants</span>
                        <span class="block font-body text-[12px] text-slate">Students can apply for slots here while scholars renew.</span>
                    </span>
                    <span class="relative mt-0.5 inline-flex shrink-0">
                        <input type="checkbox" wire:model.live="accepts_new_applicants" class="peer sr-only">
                        <span class="h-5 w-9 rounded-full bg-line transition-colors peer-checked:bg-navy-600"></span>
                        <span class="absolute left-0.5 top-0.5 size-4 rounded-full bg-surface shadow transition-transform peer-checked:translate-x-4"></span>
                    </span>
                </label>
            @endif

            @if ($this->takesNewApplicantsForm())
                <section class="space-y-3 border-t border-line pt-4">
                    <h3 class="flex items-center gap-2 font-body text-[12px] font-600 text-slate">
                        <x-ui.icon name="users" class="size-4 text-mist" />
                        Slots and eligibility
                    </h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-ui.input
                            name="total_slots"
                            :label="$this->isRenewalForm() ? 'New applicant slots' : 'Total slots'"
                            type="number"
                            min="1"
                            wire:model="total_slots"
                            required
                            class="font-mono"
                            :hint="$this->isRenewalForm() ? 'Suggested: '.$graduatingCount.' '.str('scholar')->plural($graduatingCount).' in their last term' : null"
                        />
                        <x-ui.input name="max_applications" label="Maximum applications" type="number" min="1" wire:model="max_applications" class="font-mono" hint="Leave blank for no cap" />
                        <x-ui.input name="max_monthly_income" label="Maximum family monthly income" type="number" step="0.01" min="0" wire:model="max_monthly_income" class="font-mono" hint="Leave blank for no cap" />

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
                        <fieldset>
                            <legend class="font-body text-[13px] font-500 text-ink">
                                Eligible colleges <span class="text-crimson-600">*</span>
                                @if ($this->isQuota())
                                    <span class="font-400 text-mist">· slots must add up to the total</span>
                                @endif
                            </legend>

                            @if ($colleges->isEmpty())
                                <p class="mt-1 font-body text-[13px] text-slate">
                                    No colleges yet.
                                    <a href="{{ route('admin.colleges') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Add colleges</a>
                                </p>
                            @else
                                <div class="mt-1.5 grid grid-cols-1 divide-y divide-line overflow-hidden rounded-control border border-line {{ $this->isQuota() ? '' : 'sm:grid-cols-2 sm:divide-y-0' }}">
                                    @foreach ($colleges as $college)
                                        <div class="flex items-center justify-between gap-3 px-3 py-1.5" wire:key="offering-college-{{ $college->id }}">
                                            <label class="flex min-w-0 cursor-pointer items-center gap-2 font-body text-[13px] text-ink">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $college->id }}"
                                                    wire:model.live="offeringColleges"
                                                    class="rounded border-line text-navy-600 focus-visible:ring-navy-600"
                                                >
                                                <span class="truncate">{{ $college->name }}</span>
                                            </label>

                                            @if ($this->isQuota() && in_array($college->id, $offeringColleges))
                                                <div class="w-24 shrink-0">
                                                    <label for="collegeSlots.{{ $college->id }}" class="sr-only">Slots for {{ $college->name }}</label>
                                                    <input
                                                        id="collegeSlots.{{ $college->id }}"
                                                        type="number"
                                                        min="1"
                                                        placeholder="Slots"
                                                        wire:model="collegeSlots.{{ $college->id }}"
                                                        class="block w-full rounded-control border border-line bg-surface px-2 py-1 font-mono text-[13px] text-ink focus:border-navy-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/25 @error('collegeSlots.'.$college->id) border-crimson-600 @enderror"
                                                    >
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @error('offeringColleges')
                                <p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>
                            @enderror
                            @if (collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'collegeSlots.')))
                                <p class="mt-1 font-body text-[13px] text-crimson-600">Enter the slots for every college ticked.</p>
                            @endif
                        </fieldset>
                    @endif
                </section>
            @endif

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                <x-ui.button variant="secondary" x-on:click="$wire.showForm = false">Cancel</x-ui.button>
                <x-ui.button type="submit" loading="save">{{ $editingId ? 'Save offering' : 'Add offering' }}</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
