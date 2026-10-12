{{--
    The add / edit requirement modal, shared by an offering's Requirements page
    and the Standard requirements page. Binds to the parent component's
    `showRequirementForm`, `stage`, `label`, `instructions`, `submission_mode`,
    `accepted_formats`, `max_size_mb`, `template` and `removeTemplate`, and
    submits `saveRequirement`.

    `stages` lists the stages to choose from; leave it empty when the stage is
    fixed (renewal, or a standard disbursement requirement).
--}}
@props([
    'title',
    'submitLabel',
    'stages' => [],
    'stage' => '',
    'submissionMode' => '',
    'instructionsLabel' => 'Instructions for applicants',
    'existingTemplateName' => null,
    'existingTemplateUrl' => null,
    'removeTemplate' => false,
    'template' => null,
])

@php
    $modeIcons = ['upload' => 'upload', 'physical' => 'building', 'both' => 'layers'];
    $stageIcons = ['shortlist' => 'shortlist', 'award' => 'award', 'renewal' => 'clipboard-check'];
@endphp

<x-ui.dialog
    show="$wire.showRequirementForm"
    close="$wire.showRequirementForm = false"
    size="xl"
    loading="addRequirement, editRequirement"
    :title="$title"
>
    <form wire:submit="saveRequirement" class="space-y-4">
        @if ($stages)
            <fieldset>
                <legend class="font-body text-[13px] font-500 text-ink">When is it required? <span class="text-crimson-600">*</span></legend>
                <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($stages as $stageOption)
                        <label wire:key="stage-option-{{ $stageOption->value }}" class="flex cursor-pointer items-start gap-2.5 rounded-control border px-3 py-2.5 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-navy-600 {{ $stage === $stageOption->value ? 'border-navy-600 bg-navy-50' : 'border-line hover:border-navy-100' }}">
                            <input type="radio" wire:model.live="stage" value="{{ $stageOption->value }}" class="sr-only">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-control {{ $stage === $stageOption->value ? 'bg-navy-600 text-white' : 'bg-paper text-slate' }}">
                                <x-ui.icon :name="$stageIcons[$stageOption->value]" class="size-4" />
                            </span>
                            <span>
                                <span class="block font-body text-[13px] font-600 text-ink">{{ $stageOption === \App\Enums\RequirementStage::Shortlist ? 'While shortlisted' : 'After awarding' }}</span>
                                <span class="block font-body text-[12px] text-slate">{{ $stageOption->summary() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('stage')<p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>@enderror
            </fieldset>
        @endif

        <x-ui.input name="label" label="Requirement" wire:model="label" required placeholder="Certificate of Good Moral Character" />
        <x-ui.textarea name="instructions" :label="$instructionsLabel" wire:model="instructions" rows="2" hint="Where to get it, how many copies, who signs it" />

        <fieldset>
            <legend class="font-body text-[13px] font-500 text-ink">How is it submitted? <span class="text-crimson-600">*</span></legend>
            <div class="mt-1.5 grid grid-cols-3 gap-2">
                @foreach (\App\Enums\SubmissionMode::cases() as $modeOption)
                    <label wire:key="mode-option-{{ $modeOption->value }}" title="{{ $modeOption->description() }}" class="flex cursor-pointer items-center gap-2 rounded-control border px-3 py-2 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-navy-600 {{ $submissionMode === $modeOption->value ? 'border-navy-600 bg-navy-50 text-navy-900' : 'border-line text-ink hover:border-navy-100' }}">
                        <input type="radio" wire:model.live="submission_mode" value="{{ $modeOption->value }}" class="sr-only">
                        <x-ui.icon :name="$modeIcons[$modeOption->value]" class="size-4 {{ $submissionMode === $modeOption->value ? 'text-navy-600' : 'text-mist' }}" />
                        <span class="font-body text-[13px] font-500">{{ $modeOption->label() }}</span>
                    </label>
                @endforeach
            </div>
            @if ($selectedMode = \App\Enums\SubmissionMode::tryFrom($submissionMode))
                <p class="mt-1 font-body text-[12px] text-mist">{{ $selectedMode->description() }}</p>
            @endif
            @error('submission_mode')<p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>@enderror
        </fieldset>

        @if (\App\Enums\SubmissionMode::tryFrom($submissionMode)?->needsUpload())
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_9rem]">
                <x-ui.input name="accepted_formats" label="Accepted formats" wire:model="accepted_formats" required class="font-mono" hint="Comma-separated, e.g. pdf,jpg,png" />
                <x-ui.input name="max_size_mb" label="Max size (MB)" type="number" min="1" max="50" wire:model="max_size_mb" required class="font-mono" />
            </div>
        @endif

        @if ($existingTemplateName && ! $removeTemplate && ! $template)
            <div>
                <p class="font-body text-[13px] font-500 text-ink">Blank form</p>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 rounded-control border border-line bg-paper/60 px-3 py-2">
                    <span class="inline-flex min-w-0 items-center gap-2 font-body text-[13px] text-ink">
                        <x-ui.icon name="form" class="size-4 text-navy-600" />
                        <span class="truncate">{{ $existingTemplateName }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-1">
                        @if ($existingTemplateUrl)
                            <x-ui.document-link :url="$existingTemplateUrl" :name="$existingTemplateName" class="flex size-7 items-center justify-center rounded-control text-slate hover:bg-surface hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" title="View blank form">
                                <x-ui.icon name="eye" class="size-4" />
                            </x-ui.document-link>
                        @endif
                        <button type="button" wire:click="$set('removeTemplate', true)" class="flex size-7 items-center justify-center rounded-control text-slate hover:bg-surface hover:text-crimson-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson-600" title="Remove blank form" aria-label="Remove blank form">
                            <x-ui.icon name="trash" class="size-4" />
                        </button>
                    </span>
                </div>
            </div>
        @else
            <div>
                <x-ui.file-upload
                    name="template"
                    :label="$existingTemplateName && ! $removeTemplate ? 'Replace blank form' : 'Blank form (optional)'"
                    accept=".pdf,.doc,.docx"
                    hint="PDF or Word · max 10 MB"
                />
                @if ($removeTemplate)
                    <button type="button" wire:click="$set('removeTemplate', false)" class="mt-1 font-body text-[13px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Keep {{ $existingTemplateName }}</button>
                @endif
            </div>
        @endif

        <div class="flex justify-end gap-2 border-t border-line pt-4">
            <x-ui.button variant="secondary" x-on:click="$wire.showRequirementForm = false">Cancel</x-ui.button>
            <x-ui.button type="submit" loading="saveRequirement">{{ $submitLabel }}</x-ui.button>
        </div>
    </form>
</x-ui.dialog>
