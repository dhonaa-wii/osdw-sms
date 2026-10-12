{{-- `readonly` hides the actions; `templateRoute` names the route that serves the blank form. --}}
@props(['requirement', 'first' => false, 'last' => false, 'readonly' => false, 'templateRoute' => 'staff.additional-requirements.template'])

@php
    $modeIcons = ['upload' => 'upload', 'physical' => 'building', 'both' => 'layers'];
    $iconButton = 'flex size-7 items-center justify-center rounded-control text-slate transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:opacity-30';
@endphp

<article {{ $attributes->merge(['class' => 'group flex items-start gap-3 px-4 py-2.5 transition-colors hover:bg-paper/60']) }}>
    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-600" title="{{ $requirement->submission_mode->label() }}">
        <x-ui.icon :name="$modeIcons[$requirement->submission_mode->value]" class="size-4" />
    </span>

    <div class="min-w-0 flex-1">
        <p class="font-body text-[14px] font-500 text-ink">{{ $requirement->label }}</p>
        @if ($requirement->instructions)
            <p class="line-clamp-1 font-body text-[12px] text-slate" title="{{ $requirement->instructions }}">{{ $requirement->instructions }}</p>
        @endif
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 font-body text-[12px] text-mist">
            @if ($requirement->submission_mode->needsUpload())
                <span class="inline-flex items-center gap-1">
                    <x-ui.icon name="upload" class="size-3.5" />
                    <span class="font-mono">{{ str_replace(',', ', ', $requirement->accepted_formats) }} · {{ $requirement->max_size_mb }} MB</span>
                </span>
            @endif
            @if ($requirement->submission_mode->needsPhysical())
                <span class="inline-flex items-center gap-1">
                    <x-ui.icon name="building" class="size-3.5" />
                    Physical copy
                </span>
            @endif
            @if ($requirement->template_path)
                <x-ui.document-link :url="route($templateRoute, $requirement)" :name="$requirement->template_name" :title="$requirement->label.' — blank form'" class="inline-flex items-center gap-1 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                    <x-ui.icon name="form" class="size-3.5" />
                    Blank form
                </x-ui.document-link>
            @endif
            @if (($requirement->submissions_count ?? 0) > 0)
                <span class="inline-flex items-center gap-1">
                    <x-ui.icon name="check-circle" class="size-3.5" />
                    <span class="font-mono">{{ $requirement->submissions_count }}</span> submitted
                </span>
            @endif
        </div>
    </div>

    @unless ($readonly)
    <div class="flex shrink-0 items-center gap-0.5">
        <button type="button" wire:click="moveRequirement({{ $requirement->id }}, -1)" @disabled($first) class="{{ $iconButton }} hover:text-navy-700" title="Move up" aria-label="Move {{ $requirement->label }} up">
            <x-ui.icon name="arrow-up" class="size-4" />
        </button>
        <button type="button" wire:click="moveRequirement({{ $requirement->id }}, 1)" @disabled($last) class="{{ $iconButton }} hover:text-navy-700" title="Move down" aria-label="Move {{ $requirement->label }} down">
            <x-ui.icon name="arrow-down" class="size-4" />
        </button>
        <button type="button" x-on:click="$wire.showRequirementForm = true; $wire.editRequirement({{ $requirement->id }})" class="{{ $iconButton }} hover:text-navy-700" title="Edit" aria-label="Edit {{ $requirement->label }}">
            <x-ui.icon name="pencil" class="size-4" />
        </button>
        <button
            type="button"
            x-on:click="$store.confirm.ask({
                title: 'Remove this requirement?',
                message: @js($requirement->submissions_count > 0 ? 'Files already submitted for it will be deleted.' : $requirement->label),
                confirmLabel: 'Remove requirement',
                action: () => $wire.removeRequirement({{ $requirement->id }}),
            })"
            class="{{ $iconButton }} hover:text-crimson-600"
            title="Remove"
            aria-label="Remove {{ $requirement->label }}"
        >
            <x-ui.icon name="trash" class="size-4" />
        </button>
    </div>
    @endunless
</article>
