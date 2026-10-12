<?php

use App\Enums\SubmissionMode;
use App\Livewire\Concerns\ConfiguresApplicationForm;
use App\Models\StandardFormField;
use App\Models\StandardRequirement;
use App\Services\StandardRequirementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::staff', array('title' => 'Standard requirements'))] class extends Component
{
    use ConfiguresApplicationForm;
    use WithFileUploads;

    #[Url]
    public string $section = 'form';

    // Disbursement requirement editor (modal)
    public bool $showRequirementForm = false;

    public ?int $editingRequirementId = null;

    public string $label = '';

    public string $instructions = '';

    public string $submission_mode = '';

    public string $accepted_formats = 'pdf,jpg,png';

    public string $max_size_mb = '5';

    /** A new blank form for awardees to download. */
    public $template = null;

    /** The blank form already saved on the requirement being edited. */
    public ?string $existingTemplateName = null;

    public bool $removeTemplate = false;

    public function mount(): void
    {
        $this->bootConfiguration();

        if (! in_array($this->section, ['form', 'disbursement'], true)) {
            $this->section = 'form';
        }
    }

    protected function configurationOwner(): ?Model
    {
        return null;
    }

    protected function formFieldsQuery(): Builder|HasMany
    {
        return StandardFormField::query();
    }

    protected function fieldsSaved(): void
    {
        app(StandardRequirementService::class)->syncOfferings();
    }

    public function addRequirement(): void
    {
        $this->resetRequirementForm();
        $this->showRequirementForm = true;
    }

    public function editRequirement(int $requirementId): void
    {
        $requirement = StandardRequirement::findOrFail($requirementId);

        $this->resetValidation();
        $this->editingRequirementId = $requirement->id;
        $this->label = $requirement->label;
        $this->instructions = (string) $requirement->instructions;
        $this->submission_mode = $requirement->submission_mode->value;
        $this->accepted_formats = $requirement->accepted_formats ?? 'pdf,jpg,png';
        $this->max_size_mb = (string) ($requirement->max_size_mb ?? 5);
        $this->existingTemplateName = $requirement->template_name;
        $this->showRequirementForm = true;
    }

    public function saveRequirement(StandardRequirementService $service): void
    {
        $needsUpload = SubmissionMode::tryFrom($this->submission_mode)?->needsUpload() ?? false;

        $validated = $this->validate([
            'label' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'submission_mode' => ['required', 'in:'.implode(',', array_column(SubmissionMode::cases(), 'value'))],
            'accepted_formats' => $needsUpload ? ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(\s*,\s*[a-z0-9]+)*$/i'] : ['nullable'],
            'max_size_mb' => $needsUpload ? ['required', 'integer', 'min:1', 'max:50'] : ['nullable'],
            'template' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ], [
            'submission_mode.required' => 'Choose how awardees submit it.',
            'accepted_formats.regex' => 'List file extensions separated by commas, e.g. pdf,jpg,png.',
            'template.mimes' => 'Upload the blank form as a PDF or Word file.',
            'template.max' => 'Keep the blank form under 10 MB.',
        ]);

        $service->saveRequirement([
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

    public function removeRequirement(int $requirementId, StandardRequirementService $service): void
    {
        $service->removeRequirement(StandardRequirement::findOrFail($requirementId));
    }

    public function moveRequirement(int $requirementId, int $direction, StandardRequirementService $service): void
    {
        $service->move(StandardRequirement::findOrFail($requirementId), $direction < 0 ? -1 : 1);
    }

    protected function resetRequirementForm(): void
    {
        $this->reset(['editingRequirementId', 'label', 'instructions', 'submission_mode', 'accepted_formats', 'max_size_mb', 'template', 'existingTemplateName', 'removeTemplate']);
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'requirements' => StandardRequirement::orderBy('sort_order')->orderBy('id')->get(),
        ];
    }
};
?>

@php
    $sections = [
        'form' => [
            'icon' => 'form',
            'title' => 'Application form',
            'meta' => count($fields).' '.str('field')->plural(count($fields)),
        ],
        'disbursement' => [
            'icon' => 'award',
            'title' => 'Disbursement requirements',
            'meta' => $requirements->isEmpty() ? 'None added' : $requirements->count().' '.str('requirement')->plural($requirements->count()),
        ],
    ];
    $addLink = 'inline-flex items-center gap-1 rounded-control px-2.5 py-1.5 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600';
@endphp

{{-- Sections switch in the browser, so changing tabs never waits on the server. --}}
<div x-data="{ section: $wire.entangle('section').live }">
    <x-ui.back-link :href="route('admin.scholarship-programs')" label="Scholarship programs" />

    <h1 class="mt-3 font-display text-[22px] font-600 text-ink sm:text-[26px]">Standard requirements</h1>

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

    {{-- Application form --}}
    <section x-show="section === 'form'" @if ($section !== 'form') x-cloak @endif class="mt-5" aria-labelledby="section-form-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="section-form-heading" class="font-display text-[18px] font-600 text-ink">Application form</h2>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" x-on:click="$wire.showPreview = true">
                    <x-ui.icon name="eye" class="size-4" /> View form
                </x-ui.button>
                <x-ui.button wire:click="saveFields" loading="saveFields">Save application form</x-ui.button>
            </div>
        </div>

        <x-config.field-builder :fields="$fields" :editing-field="$editingField" />
    </section>

    {{-- Disbursement requirements --}}
    <section x-show="section === 'disbursement'" @if ($section !== 'disbursement') x-cloak @endif class="mt-5" aria-labelledby="section-disbursement-heading">
        <h2 id="section-disbursement-heading" class="font-display text-[18px] font-600 text-ink">Disbursement requirements</h2>

        <div class="mt-4 overflow-hidden rounded-card border border-line bg-surface lg:max-w-3xl">
            <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                    <x-ui.icon name="award" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-display text-[15px] font-600 text-ink">After awarding</h3>
                        <span class="font-mono text-[12px] text-mist">{{ $requirements->count() }}</span>
                    </div>
                    <p class="font-body text-[12px] text-mist">{{ \App\Enums\RequirementStage::Award->summary() }}</p>
                </div>
            </div>

            <div class="divide-y divide-line">
                @forelse ($requirements as $requirement)
                    <x-config.requirement-row
                        :requirement="$requirement"
                        :first="$loop->first"
                        :last="$loop->last"
                        template-route="admin.scholarship-programs.standard-requirements.template"
                        wire:key="requirement-{{ $requirement->id }}"
                    />
                @empty
                    <div class="px-4 py-8 text-center">
                        <span class="mx-auto flex size-10 items-center justify-center rounded-full bg-paper text-mist">
                            <x-ui.icon name="award" class="size-5" />
                        </span>
                        <p class="mt-2 font-body text-[13px] text-slate">No disbursement requirements yet.</p>
                    </div>
                @endforelse
            </div>

            <div class="border-t border-line px-2 py-1.5">
                <button type="button" x-on:click="$wire.showRequirementForm = true; $wire.addRequirement()" class="{{ $addLink }}">
                    <x-ui.icon name="plus" class="size-4" /> Add requirement
                </button>
            </div>
        </div>
    </section>

    <x-config.requirement-dialog
        :title="$editingRequirementId ? 'Edit requirement' : 'Add disbursement requirement'"
        :submit-label="$editingRequirementId ? 'Save requirement' : 'Add requirement'"
        :submission-mode="$submission_mode"
        instructions-label="Instructions for awardees"
        :existing-template-name="$existingTemplateName"
        :existing-template-url="$existingTemplateName ? route('admin.scholarship-programs.standard-requirements.template', $editingRequirementId) : null"
        :remove-template="$removeTemplate"
        :template="$template"
    />

    <x-config.form-preview :fields="$fields" />
</div>
