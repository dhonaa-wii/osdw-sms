<?php

use App\Enums\EligibilityField;
use App\Enums\EligibilityOperator;
use App\Enums\RequirementStage;
use App\Enums\SubmissionMode;
use App\Livewire\Concerns\ConfiguresApplicationForm;
use App\Models\OfferingAdditionalRequirement;
use App\Models\ProgramOffering;
use App\Services\OfferingRequirementService;
use App\Services\StandardRequirementService;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::staff', array('title' => 'Requirements'))] class extends Component
{
    use ConfiguresApplicationForm {
        saveFields as protected saveOwnFields;
    }
    use WithFileUploads;

    public ProgramOffering $offering;

    /**
     * Which section is open. A renewal offering has only its renewal
     * requirements; any other offering has the form, additional
     * requirements and interview.
     */
    #[Url]
    public string $section = '';

    // Additional requirement editor (modal)
    public bool $showRequirementForm = false;

    public ?int $editingRequirementId = null;

    public string $stage = '';

    public string $label = '';

    public string $instructions = '';

    public string $submission_mode = '';

    public string $accepted_formats = 'pdf,jpg,png';

    public string $max_size_mb = '5';

    /** A new blank form for applicants to download. */
    public $template = null;

    /** The blank form already saved on the requirement being edited. */
    public ?string $existingTemplateName = null;

    public bool $removeTemplate = false;

    // Interview
    public bool $has_interview = false;

    public string $interview_at = '';

    public string $interview_venue = '';

    public string $interview_instructions = '';

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm', 'renewsOffering.academicTerm');
        $this->bootConfiguration();
        $this->fillInterview();

        if (! in_array($this->section, $this->sections(), true)) {
            $this->section = $this->sections()[0];
        }
    }

    /**
     * @return array<int, string>
     */
    public function sections(): array
    {
        $applicantSections = $this->offering->takesNewApplicants() ? ['form', 'additional', 'interview'] : [];

        return $this->offering->isRenewal() ? ['renewal', ...$applicantSections] : $applicantSections;
    }

    protected function configurationOwner(): Model
    {
        return $this->offering;
    }

    public function openSection(string $section): void
    {
        $this->section = in_array($section, $this->sections(), true) ? $section : $this->sections()[0];
    }

    /**
     * Copy the standard application form and disbursement requirements into
     * this offering; they stay read-only here while it uses them.
     */
    public function useStandardRequirements(StandardRequirementService $service): void
    {
        try {
            $service->useStandard($this->offering);
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Standard requirements not applied', message: $exception->getMessage());

            return;
        }

        $this->offering->refresh();
        $this->loadFields();
        $this->editingField = null;
        $this->dispatch('notice', title: 'Standard requirements applied', message: $this->offering->scholarshipProgram->name.' · '.$this->offering->displayName());
    }

    public function setRequirementsManually(StandardRequirementService $service): void
    {
        $service->setManually($this->offering);
        $this->offering->refresh();
    }

    public function saveFields(): void
    {
        if ($this->offering->usesStandardRequirements()) {
            $this->dispatch('notice', tone: 'error', title: 'Application form not saved', message: 'This offering uses the standard requirements. Choose Set manually to edit its form.');

            return;
        }

        $this->saveOwnFields();
    }

    /**
     * The required GWA stays per offering, including one that uses the
     * standard form.
     */
    public function saveGwa(): void
    {
        $this->validate(['required_gwa' => ['required', 'numeric', 'min:0', 'max:100']]);

        $this->offering->eligibilityRules()->updateOrCreate(
            ['field' => EligibilityField::Gwa->value],
            [
                'operator' => EligibilityOperator::Gte->value,
                'value' => $this->required_gwa,
                'message' => "You need a GWA of at least {$this->required_gwa} to apply for this scholarship.",
            ]
        );

        $this->dispatch('notice', title: 'Required GWA saved', message: $this->required_gwa);
    }

    /**
     * A requirement this page may change: disbursement requirements belong
     * to the standard requirements while the offering uses them.
     */
    protected function editableRequirement(int $requirementId): OfferingAdditionalRequirement
    {
        $requirement = $this->offering->additionalRequirements()->findOrFail($requirementId);

        abort_if($this->offering->usesStandardRequirements() && $requirement->stage === RequirementStage::Award, 403);

        return $requirement;
    }

    /**
     * @return array<int, RequirementStage>
     */
    public function requirementStages(): array
    {
        return array_values(array_filter(
            RequirementStage::forOffering($this->offering),
            fn (RequirementStage $stage) => ! ($stage === RequirementStage::Award && $this->offering->usesStandardRequirements())
        ));
    }

    public function addRequirement(string $stage = ''): void
    {
        $this->resetRequirementForm();
        $this->stage = $this->section === 'renewal'
            ? RequirementStage::Renewal->value
            : RequirementStage::tryFrom($stage)?->value ?? '';
        $this->showRequirementForm = true;
    }

    public function editRequirement(int $requirementId): void
    {
        $requirement = $this->editableRequirement($requirementId);

        $this->resetValidation();
        $this->editingRequirementId = $requirement->id;
        $this->stage = $requirement->stage->value;
        $this->label = $requirement->label;
        $this->instructions = (string) $requirement->instructions;
        $this->submission_mode = $requirement->submission_mode->value;
        $this->accepted_formats = $requirement->accepted_formats ?? 'pdf,jpg,png';
        $this->max_size_mb = (string) ($requirement->max_size_mb ?? 5);
        $this->existingTemplateName = $requirement->template_name;
        $this->showRequirementForm = true;
    }

    public function cancelRequirement(): void
    {
        $this->showRequirementForm = false;
        $this->resetRequirementForm();
    }

    public function saveRequirement(OfferingRequirementService $service): void
    {
        $needsUpload = SubmissionMode::tryFrom($this->submission_mode)?->needsUpload() ?? false;

        $validated = $this->validate([
            'stage' => ['required', 'in:'.implode(',', array_column($this->requirementStages(), 'value'))],
            'label' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'submission_mode' => ['required', 'in:'.implode(',', array_column(SubmissionMode::cases(), 'value'))],
            'accepted_formats' => $needsUpload ? ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(\s*,\s*[a-z0-9]+)*$/i'] : ['nullable'],
            'max_size_mb' => $needsUpload ? ['required', 'integer', 'min:1', 'max:50'] : ['nullable'],
            'template' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ], [
            'stage.required' => 'Choose when this requirement applies.',
            'submission_mode.required' => 'Choose how applicants submit it.',
            'accepted_formats.regex' => 'List file extensions separated by commas, e.g. pdf,jpg,png.',
            'template.mimes' => 'Upload the blank form as a PDF or Word file.',
            'template.max' => 'Keep the blank form under 10 MB.',
        ]);

        if ($this->editingRequirementId !== null) {
            $this->editableRequirement($this->editingRequirementId);
        }

        $service->saveRequirement($this->offering, [
            'stage' => $validated['stage'],
            'label' => trim($validated['label']),
            'instructions' => trim((string) $validated['instructions']) ?: null,
            'submission_mode' => $validated['submission_mode'],
            'accepted_formats' => $needsUpload ? strtolower(preg_replace('/\s+/', '', $validated['accepted_formats'])) : null,
            'max_size_mb' => $needsUpload ? (int) $validated['max_size_mb'] : null,
        ], $this->editingRequirementId, $this->template, $this->removeTemplate);

        $this->dispatch('notice', title: $this->editingRequirementId ? 'Requirement updated' : 'Requirement added', message: trim($validated['label']));
        $this->showRequirementForm = false;
        $this->resetRequirementForm();
    }

    public function removeRequirement(int $requirementId, OfferingRequirementService $service): void
    {
        $service->removeRequirement($this->editableRequirement($requirementId));
    }

    public function moveRequirement(int $requirementId, int $direction, OfferingRequirementService $service): void
    {
        $service->move($this->editableRequirement($requirementId), $direction < 0 ? -1 : 1);
    }

    public function saveInterview(OfferingRequirementService $service): void
    {
        $validated = $this->validate([
            'has_interview' => ['boolean'],
            'interview_at' => $this->has_interview ? ['required', 'date'] : ['nullable'],
            'interview_venue' => $this->has_interview ? ['required', 'string', 'max:255'] : ['nullable'],
            'interview_instructions' => ['nullable', 'string', 'max:2000'],
        ], [
            'interview_at.required' => 'Set the interview date and time.',
            'interview_venue.required' => 'Enter where applicants should go, e.g. OSDW Conference Room.',
        ]);

        $service->saveInterview($this->offering, [
            'has_interview' => $validated['has_interview'],
            'interview_at' => $validated['interview_at'] ?: null,
            'interview_venue' => trim((string) $validated['interview_venue']) ?: null,
            'interview_instructions' => trim((string) $validated['interview_instructions']) ?: null,
        ]);

        $this->offering->refresh();
        $this->fillInterview();
        $this->dispatch('notice', title: $this->has_interview ? 'Interview details saved' : 'Interview turned off');
    }

    protected function fillInterview(): void
    {
        $this->has_interview = (bool) $this->offering->has_interview;
        $this->interview_at = $this->offering->interview_at?->format('Y-m-d\TH:i') ?? '';
        $this->interview_venue = (string) $this->offering->interview_venue;
        $this->interview_instructions = (string) $this->offering->interview_instructions;
    }

    protected function resetRequirementForm(): void
    {
        $this->reset(['editingRequirementId', 'stage', 'label', 'instructions', 'submission_mode', 'accepted_formats', 'max_size_mb', 'template', 'existingTemplateName', 'removeTemplate']);
        $this->resetValidation();
    }

    public function with(): array
    {
        $requirements = $this->offering->additionalRequirements()->withCount('submissions')->get();

        return [
            'requirementsByStage' => collect(RequirementStage::cases())->mapWithKeys(fn ($stage) => [
                $stage->value => $requirements->filter(fn (OfferingAdditionalRequirement $requirement) => $requirement->stage === $stage)->values(),
            ]),
            'requirementCount' => $requirements->count(),
            'canUseStandard' => app(StandardRequirementService::class)->canUseStandard($this->offering),
        ];
    }
};
?>

@php
    $modeIcons = ['upload' => 'upload', 'physical' => 'building', 'both' => 'layers'];
    $stageIcons = ['shortlist' => 'shortlist', 'award' => 'award', 'renewal' => 'clipboard-check'];
    $shownStages = \App\Enums\RequirementStage::forApplicants();
    $usesStandard = $offering->usesStandardRequirements();
    // The stage picker shows only when there is a choice to make.
    $requirementStages = array_values(array_filter($this->requirementStages(), fn ($stage) => $stage !== \App\Enums\RequirementStage::Renewal));
    $requirementStages = count($requirementStages) > 1 ? $requirementStages : [];
    $sections = ! $offering->takesNewApplicants() ? [] : [
        ...($offering->isRenewal() ? ['renewal' => [
            'icon' => 'award',
            'title' => 'Renewal requirements',
            'meta' => $requirementsByStage['renewal']->count() === 0
                ? 'None added'
                : $requirementsByStage['renewal']->count().' '.str('requirement')->plural($requirementsByStage['renewal']->count()),
        ]] : []),
        'form' => [
            'icon' => 'form',
            'title' => 'Application form',
            'meta' => count($fields).' '.str('field')->plural(count($fields)),
        ],
        'additional' => [
            'icon' => 'clipboard-check',
            'title' => 'Additional requirements',
            'meta' => $requirementsByStage['shortlist']->isEmpty() && $requirementsByStage['award']->isEmpty()
                ? 'None added'
                : $requirementsByStage['shortlist']->count().' shortlist · '.$requirementsByStage['award']->count().' awarded',
        ],
        'interview' => [
            'icon' => 'calendar',
            'title' => 'Interview',
            'meta' => ! $offering->has_interview
                ? 'Not required'
                : ($offering->interview_at ? $offering->interview_at->format('M j, Y · g:i A') : 'Date not set'),
        ],
    ];
    $cardHeader = 'flex items-center gap-3 border-b border-line px-4 py-3';
    $headerIcon = 'flex size-8 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600';
    $addLink = 'inline-flex items-center gap-1 rounded-control px-2.5 py-1.5 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600';
@endphp

{{-- Sections switch in the browser, so changing tabs never waits on the server. --}}
<div x-data="{ section: $wire.entangle('section').live }">
    <x-ui.back-link :href="route('admin.offerings.view', $offering)" label="{{ $offering->scholarshipProgram->name }} · {{ $offering->displayName() }}" />

    <h1 class="mt-3 font-display text-[22px] font-600 text-ink sm:text-[26px]">{{ $offering->scholarshipProgram->name }} · {{ $offering->displayName() }} {{ $offering->takesNewApplicants() ? 'requirements' : 'renewal requirements' }}</h1>

    {{-- Where the application form and disbursement requirements come from --}}
    @if ($offering->takesNewApplicants())
        @php $segment = 'flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-[6px] px-3 py-1.5 font-body text-[13px] font-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:cursor-not-allowed disabled:opacity-50'; @endphp
        <div class="mt-4 flex flex-col gap-3 rounded-card border border-line bg-surface px-4 py-3 md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <span class="{{ $headerIcon }}">
                    <x-ui.icon name="layers" class="size-4" />
                </span>
                <div class="min-w-0">
                    <p class="font-body text-[14px] font-600 text-ink">Application form and disbursement requirements</p>
                    @if ($usesStandard)
                        <a href="{{ route('admin.scholarship-programs.standard-requirements') }}" wire:navigate class="rounded-sm font-body text-[12px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Edit standard requirements</a>
                    @elseif (! $canUseStandard)
                        <p class="font-body text-[12px] text-mist">Applications have come in, so this offering keeps its own form.</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 rounded-control bg-paper p-1" role="radiogroup" aria-label="Requirements source">
                @if ($usesStandard)
                    <button type="button" role="radio" aria-checked="true" class="{{ $segment }} bg-surface text-ink shadow-sm">
                        <x-ui.icon name="clipboard-check" class="size-4" /> Standard requirements
                    </button>
                    <button
                        type="button"
                        role="radio"
                        aria-checked="false"
                        wire:click="setRequirementsManually"
                        wire:loading.attr="disabled"
                        wire:target="setRequirementsManually"
                        class="{{ $segment }} text-slate hover:text-ink"
                    >
                        <x-ui.icon name="pencil" class="size-4" /> Set manually
                    </button>
                @else
                    <button
                        type="button"
                        role="radio"
                        aria-checked="false"
                        @disabled(! $canUseStandard)
                        x-on:click="$store.confirm.ask({
                            title: 'Use the standard requirements?',
                            message: 'The standard application form and disbursement requirements replace this offering\'s own.',
                            confirmLabel: 'Use standard requirements',
                            action: () => $wire.useStandardRequirements(),
                        })"
                        wire:loading.attr="disabled"
                        wire:target="useStandardRequirements"
                        class="{{ $segment }} text-slate hover:text-ink"
                    >
                        <x-ui.icon name="clipboard-check" class="size-4" /> Standard requirements
                    </button>
                    <button type="button" role="radio" aria-checked="true" class="{{ $segment }} bg-surface text-ink shadow-sm">
                        <x-ui.icon name="pencil" class="size-4" /> Set manually
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Section switcher --}}
    @if ($sections)
        <nav class="mt-4 flex gap-1 overflow-x-auto rounded-card border border-line bg-surface p-1" aria-label="Requirement sections">
            @foreach ($sections as $key => $item)
                <button
                    type="button"
                    wire:key="section-{{ $key }}"
                    x-on:click="section = '{{ $key }}'"
                    :aria-current="section === '{{ $key }}' ? 'page' : null"
                    :class="section === '{{ $key }}' ? 'bg-navy-50 text-navy-900' : 'text-slate hover:bg-paper hover:text-ink'"
                    class="flex min-w-[11rem] flex-1 items-center gap-2.5 rounded-control px-3 py-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-control transition-colors"
                        :class="section === '{{ $key }}' ? 'bg-navy-600 text-white' : 'bg-paper text-slate'"
                    >
                        <x-ui.icon :name="$item['icon']" class="size-4" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-body text-[13px] font-600">{{ $item['title'] }}</span>
                        <span class="block truncate font-body text-[12px] text-mist">{{ $item['meta'] }}</span>
                    </span>
                </button>
            @endforeach
        </nav>
    @endif

    {{-- Application form --}}
    @if (in_array('form', $this->sections(), true))
        <section x-show="section === 'form'" @if ($section !== 'form') x-cloak @endif class="mt-5" aria-labelledby="section-form-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="section-form-heading" class="font-display text-[18px] font-600 text-ink">Application form</h2>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" x-on:click="$wire.showPreview = true">
                        <x-ui.icon name="eye" class="size-4" /> View form
                    </x-ui.button>
                    @if (! $usesStandard)
                        <x-ui.button wire:click="saveFields" loading="saveFields">Save application form</x-ui.button>
                    @elseif ($this->isAcademic())
                        <x-ui.button wire:click="saveGwa" loading="saveGwa">Save required GWA</x-ui.button>
                    @endif
                </div>
            </div>

            <x-config.field-builder :fields="$fields" :is-academic="$this->isAcademic()" :editing-field="$editingField" :readonly="$usesStandard" />
        </section>
    @endif

    {{-- Additional requirements --}}
    @if (in_array('additional', $this->sections(), true))
        <section x-show="section === 'additional'" @if ($section !== 'additional') x-cloak @endif class="mt-5" aria-labelledby="section-additional-heading">
            <h2 id="section-additional-heading" class="font-display text-[18px] font-600 text-ink">Additional requirements</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
                @foreach ($shownStages as $stageCase)
                    @php
                        $stageRequirements = $requirementsByStage[$stageCase->value];
                        $fromStandard = $usesStandard && $stageCase === \App\Enums\RequirementStage::Award;
                    @endphp
                    <div class="overflow-hidden rounded-card border border-line bg-surface" wire:key="stage-{{ $stageCase->value }}">
                        <div class="{{ $cardHeader }}">
                            <span class="{{ $headerIcon }}">
                                <x-ui.icon :name="$stageIcons[$stageCase->value]" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-display text-[15px] font-600 text-ink">{{ $stageCase->heading() }}</h3>
                                    <span class="font-mono text-[12px] text-mist">{{ $stageRequirements->count() }}</span>
                                </div>
                                <p class="font-body text-[12px] text-mist">{{ $stageCase->summary() }}</p>
                            </div>
                        </div>

                        <div class="divide-y divide-line">
                            @forelse ($stageRequirements as $requirement)
                                <x-config.requirement-row :requirement="$requirement" :first="$loop->first" :last="$loop->last" :readonly="$fromStandard" wire:key="requirement-{{ $requirement->id }}" />
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-paper text-mist">
                                        <x-ui.icon :name="$stageIcons[$stageCase->value]" class="size-5" />
                                    </span>
                                    <p class="mt-2 font-body text-[13px] text-slate">No {{ strtolower($stageCase->heading()) }} yet.</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="border-t border-line px-2 py-1.5">
                            @if ($fromStandard)
                                <a href="{{ route('admin.scholarship-programs.standard-requirements') }}" wire:navigate class="{{ $addLink }}">
                                    <x-ui.icon name="pencil" class="size-4" /> Edit standard requirements
                                </a>
                            @else
                                <button type="button" x-on:click="$wire.showRequirementForm = true; $wire.addRequirement('{{ $stageCase->value }}')" class="{{ $addLink }}">
                                    <x-ui.icon name="plus" class="size-4" /> Add requirement
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Renewal requirements --}}
    @if (in_array('renewal', $this->sections(), true))
        @php
            $renewalRequirements = $requirementsByStage['renewal'];
            $uploadCount = $renewalRequirements->filter(fn ($requirement) => $requirement->submission_mode->needsUpload())->count();
            $physicalCount = $renewalRequirements->filter(fn ($requirement) => $requirement->submission_mode->needsPhysical())->count();
        @endphp

        <div x-show="section === 'renewal'" @if ($section !== 'renewal') x-cloak @endif>
            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-card border border-line bg-surface p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                        <x-ui.icon name="calendar" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-body text-[12px] text-slate">Submission window</p>
                        <p class="font-mono text-[14px] text-ink">{{ $offering->opens_at->format('M j') }} – {{ $offering->closes_at->format('M j, Y') }}</p>
                        <div class="mt-1"><x-ui.status-stamp :status="$offering->registrationStatus()" /></div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-card border border-line bg-surface p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                        <x-ui.icon name="award" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-body text-[12px] text-slate">Renews scholars of</p>
                        <p class="truncate font-body text-[14px] font-600 text-ink">{{ $offering->renewsOffering->displayName() }}</p>
                        <p class="font-body text-[12px] text-mist">{{ $offering->renewsOffering->academicTerm->school_year }} · {{ $offering->renewsOffering->academicTerm->semester->label() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-card border border-line bg-surface p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                        <x-ui.icon name="clipboard-check" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-body text-[12px] text-slate">Requirements</p>
                        <p class="font-mono text-[18px] leading-tight text-ink">{{ $renewalRequirements->count() }}</p>
                        <p class="font-body text-[12px] text-mist"><span class="font-mono">{{ $uploadCount }}</span> upload · <span class="font-mono">{{ $physicalCount }}</span> physical</p>
                    </div>
                </div>
            </div>

            <section class="mt-5" aria-labelledby="section-renewal-heading">
                <h2 id="section-renewal-heading" class="font-display text-[18px] font-600 text-ink">Renewal requirements</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_300px] lg:items-start">
                    <div class="overflow-hidden rounded-card border border-line bg-surface">
                        <div class="{{ $cardHeader }}">
                            <span class="{{ $headerIcon }}">
                                <x-ui.icon name="clipboard-check" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-display text-[15px] font-600 text-ink">To hand in</h3>
                                    <span class="font-mono text-[12px] text-mist">{{ $renewalRequirements->count() }}</span>
                                </div>
                                <p class="font-body text-[12px] text-mist">{{ \App\Enums\RequirementStage::Renewal->summary() }}</p>
                            </div>
                        </div>

                        <div class="divide-y divide-line">
                            @forelse ($renewalRequirements as $requirement)
                                <x-config.requirement-row :requirement="$requirement" :first="$loop->first" :last="$loop->last" wire:key="requirement-{{ $requirement->id }}" />
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-paper text-mist">
                                        <x-ui.icon name="clipboard-check" class="size-5" />
                                    </span>
                                    <p class="mt-2 font-body text-[13px] text-slate">No renewal requirements yet.</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="border-t border-line px-2 py-1.5">
                            <button type="button" x-on:click="$wire.showRequirementForm = true; $wire.addRequirement('renewal')" class="{{ $addLink }}">
                                <x-ui.icon name="plus" class="size-4" /> Add renewal requirement
                            </button>
                        </div>
                    </div>

                    <aside class="lg:sticky lg:top-20" aria-label="Scholar view">
                        <p class="font-body text-[12px] font-600 text-slate">What renewing scholars see</p>
                        <div class="mt-2 overflow-hidden rounded-card border border-line bg-surface">
                            <div class="flex items-center gap-3 border-b border-line bg-paper/60 px-4 py-2.5">
                                <span class="flex size-8 items-center justify-center rounded-control bg-navy-600 text-white">
                                    <x-ui.icon name="clipboard-check" class="size-4" />
                                </span>
                                <div>
                                    <p class="font-display text-[14px] font-600 text-ink">Requirements to renew</p>
                                    <p class="font-body text-[12px] text-slate">Submit by <span class="font-mono text-ink">{{ $offering->closes_at->format('M j, Y · g:i A') }}</span></p>
                                </div>
                            </div>
                            @if ($renewalRequirements->isEmpty())
                                <p class="px-4 py-6 text-center font-body text-[13px] text-mist">No requirements listed.</p>
                            @else
                                <ol class="divide-y divide-line">
                                    @foreach ($renewalRequirements as $requirement)
                                        <li class="flex items-start gap-3 px-4 py-2.5" wire:key="renewal-preview-{{ $requirement->id }}">
                                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full border border-line bg-paper font-mono text-[11px] text-slate">{{ $loop->iteration }}</span>
                                            <div class="min-w-0">
                                                <p class="font-body text-[13px] text-ink">{{ $requirement->label }}</p>
                                                <p class="inline-flex items-center gap-1 font-body text-[12px] text-mist">
                                                    <x-ui.icon :name="$modeIcons[$requirement->submission_mode->value]" class="size-3.5" />
                                                    {{ $requirement->submission_mode->label() }}
                                                </p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </aside>
                </div>
            </section>
        </div>
    @endif

    {{-- Interview --}}
    @if (in_array('interview', $this->sections(), true))
        <section x-show="section === 'interview'" @if ($section !== 'interview') x-cloak @endif class="mt-5" aria-labelledby="section-interview-heading">
            <h2 id="section-interview-heading" class="font-display text-[18px] font-600 text-ink">Interview</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px] lg:items-start">
                <form wire:submit="saveInterview" class="overflow-hidden rounded-card border border-line bg-surface">
                    <label class="flex cursor-pointer items-center justify-between gap-4 border-b border-line px-4 py-3">
                        <span>
                            <span class="block font-body text-[14px] font-600 text-ink">Shortlisted applicants must attend an interview</span>
                            <span class="block font-body text-[12px] text-mist">The schedule shows on each shortlisted applicant's tracking page.</span>
                        </span>
                        <span class="relative inline-flex shrink-0">
                            <input type="checkbox" wire:model.live="has_interview" class="peer sr-only">
                            <span class="h-5 w-9 rounded-full bg-line transition-colors peer-checked:bg-navy-600 peer-focus-visible:ring-2 peer-focus-visible:ring-navy-600 peer-focus-visible:ring-offset-2"></span>
                            <span class="absolute left-0.5 top-0.5 size-4 rounded-full bg-surface shadow transition-transform peer-checked:translate-x-4"></span>
                        </span>
                    </label>

                    @if ($has_interview)
                        <div class="space-y-3 px-4 py-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-ui.input name="interview_at" label="Date and time" type="datetime-local" wire:model.live.debounce.400ms="interview_at" required />
                                <x-ui.input name="interview_venue" label="Venue" wire:model.live.debounce.400ms="interview_venue" required placeholder="OSDW Conference Room, Admin Building" />
                            </div>
                            <x-ui.textarea name="interview_instructions" label="Instructions for applicants" wire:model.live.debounce.400ms="interview_instructions" rows="4" hint="What to bring, what to wear, who to look for" />
                        </div>
                    @else
                        <div class="flex items-center gap-2 px-4 py-4 font-body text-[13px] text-slate">
                            <x-ui.icon name="info" class="size-4 text-mist" />
                            Turn this on to set a date, venue and instructions.
                        </div>
                    @endif

                    <div class="flex justify-end border-t border-line px-4 py-3">
                        <x-ui.button type="submit" loading="saveInterview">Save interview details</x-ui.button>
                    </div>
                </form>

                <aside class="lg:sticky lg:top-20" aria-label="Applicant view">
                    <p class="font-body text-[12px] font-600 text-slate">What shortlisted applicants see</p>
                    @if ($has_interview)
                        @php $previewAt = $interview_at !== '' ? rescue(fn () => \Illuminate\Support\Carbon::parse($interview_at), null, false) : null; @endphp
                        <div class="mt-2 overflow-hidden rounded-card border border-line bg-surface">
                            <div class="flex items-center gap-3 border-b border-line bg-paper/60 px-4 py-2.5">
                                <span class="flex size-8 items-center justify-center rounded-control bg-azure-600 text-white">
                                    <x-ui.icon name="calendar" class="size-4" />
                                </span>
                                <p class="font-display text-[14px] font-600 text-ink">Interview</p>
                            </div>
                            <dl class="space-y-2.5 px-4 py-3">
                                <div class="flex items-start gap-2.5">
                                    <x-ui.icon name="clock" class="mt-0.5 size-4 text-slate" />
                                    <div>
                                        <dt class="sr-only">When</dt>
                                        <dd class="font-mono text-[13px] text-ink">{{ $previewAt ? $previewAt->format('D, M j, Y · g:i A') : 'Date and time' }}</dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <x-ui.icon name="map-pin" class="mt-0.5 size-4 text-slate" />
                                    <div>
                                        <dt class="sr-only">Where</dt>
                                        <dd class="font-body text-[13px] {{ $interview_venue !== '' ? 'text-ink' : 'text-mist' }}">{{ $interview_venue !== '' ? $interview_venue : 'Venue' }}</dd>
                                    </div>
                                </div>
                                @if (trim($interview_instructions) !== '')
                                    <div class="border-t border-line pt-2.5">
                                        <dt class="sr-only">Instructions</dt>
                                        <dd class="whitespace-pre-line font-body text-[13px] text-ink">{{ $interview_instructions }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @else
                        <div class="mt-2 rounded-card border border-dashed border-line px-4 py-6 text-center font-body text-[13px] text-mist">No interview notice.</div>
                    @endif
                </aside>
            </div>
        </section>
    @endif

    {{-- Add / edit requirement --}}
    <x-config.requirement-dialog
        :title="$editingRequirementId ? 'Edit requirement' : ($stage === 'renewal' ? 'Add renewal requirement' : 'Add requirement')"
        :submit-label="$editingRequirementId ? 'Save requirement' : ($stage === 'renewal' ? 'Add renewal requirement' : 'Add requirement')"
        :stages="$stage === 'renewal' ? [] : $requirementStages"
        :stage="$stage"
        :submission-mode="$submission_mode"
        :instructions-label="$stage === 'renewal' ? 'Instructions for scholars' : 'Instructions for applicants'"
        :existing-template-name="$existingTemplateName"
        :existing-template-url="$existingTemplateName ? route('staff.additional-requirements.template', $editingRequirementId) : null"
        :remove-template="$removeTemplate"
        :template="$template"
    />

    <x-config.form-preview
        :fields="$fields"
        :is-academic="$this->isAcademic()"
    />
</div>
