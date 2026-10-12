{{-- `readonly` lists the fields without editing them (an offering using the standard requirements). --}}
@props(['fields', 'isAcademic' => false, 'editingField' => null, 'readonly' => false])

@php
    $systemFields = [
        ['icon' => 'hash', 'label' => 'Student ID'],
        ['icon' => 'user', 'label' => 'Name'],
        ['icon' => 'user', 'label' => 'Sex'],
        ['icon' => 'calendar', 'label' => 'Birthdate'],
        ['icon' => 'mail', 'label' => 'Email'],
        ['icon' => 'phone', 'label' => 'Mobile number'],
        ['icon' => 'map-pin', 'label' => 'Address'],
        ['icon' => 'building', 'label' => 'College'],
        ['icon' => 'layers', 'label' => 'Year level'],
        ['icon' => 'users', 'label' => 'Family monthly income'],
    ];
@endphp

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_300px] lg:items-start">
    <div class="overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex items-center gap-3 border-b border-line px-4 py-3">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                <x-ui.icon name="form" class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-display text-[15px] font-600 text-ink">Form fields</h3>
                    <span class="font-mono text-[12px] text-mist">{{ count($fields) }}</span>
                </div>
                <p class="font-body text-[12px] text-mist">Asked on the application after the system fields.</p>
            </div>
        </div>

        <div class="p-2">
            @forelse ($fields as $index => $field)
                @php
                    $fieldType = \App\Enums\FieldType::from($field['field_type']);
                    $hasErrors = $errors->has("fields.{$index}.*");
                    $open = ! $readonly && ($editingField === $index || $hasErrors);
                    $choiceCount = $fieldType->needsOptions() ? count(array_filter(array_map('trim', explode("\n", $field['options'])))) : 0;
                @endphp
                <article
                    wire:key="field-row-{{ $index }}"
                    class="rounded-control transition-colors {{ $loop->first ? '' : 'mt-1 border-t border-line' }} {{ $open ? 'bg-paper' : 'hover:bg-paper' }}"
                >
                    <div class="flex items-start gap-3 px-2 py-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border {{ $hasErrors ? 'border-crimson-600 text-crimson-600' : 'border-line text-navy-700' }} bg-surface" title="{{ $fieldType->label() }}">
                            <x-ui.icon :name="$fieldType->icon()" class="size-4" />
                        </span>

                        <button type="button" wire:click="toggleField({{ $index }})" @disabled($readonly) class="min-w-0 flex-1 rounded-control text-left disabled:cursor-default focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600" aria-expanded="{{ $open ? 'true' : 'false' }}">
                            <span class="block font-body text-[14px] font-500 {{ $field['label'] !== '' ? 'text-ink' : 'text-mist' }}">{{ $field['label'] !== '' ? $field['label'] : 'Untitled field' }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 font-body text-[12px] text-mist">
                                <span>{{ $fieldType->label() }}</span>
                                <span class="{{ $field['is_required'] ? 'text-ink' : '' }}">{{ $field['is_required'] ? 'Required' : 'Optional' }}</span>
                                @if ($fieldType->needsOptions())
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-ui.icon name="list" class="size-3.5 text-navy-700" />
                                        <span class="font-mono">{{ $choiceCount }}</span> {{ str('choice')->plural($choiceCount) }}
                                    </span>
                                @endif
                                @if ($fieldType->isFile())
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-ui.icon name="upload" class="size-3.5 text-navy-700" />
                                        <span class="font-mono">{{ str_replace(',', ', ', $field['accepted_formats']) }}</span> &middot; <span class="font-mono">{{ $field['max_size_mb'] }} MB</span>
                                    </span>
                                    @if ($field['requires_physical_copy'])
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-ui.icon name="building" class="size-3.5 text-navy-700" />
                                            Physical copy at OSDW
                                        </span>
                                    @endif
                                @endif
                                @if ($field['description'] !== '' && ! $open)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-ui.icon name="info" class="size-3.5 text-navy-700" />
                                        Helper text
                                    </span>
                                @endif
                            </span>
                        </button>

                        @unless ($readonly)
                        <div class="flex shrink-0 items-start gap-0.5">
                            <button type="button" wire:click="moveFieldUp({{ $index }})" @disabled($loop->first) class="flex size-7 items-center justify-center rounded-control text-slate hover:bg-surface hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:opacity-30" aria-label="Move {{ $field['label'] ?: 'field' }} up">
                                <x-ui.icon name="arrow-up" class="size-4" />
                            </button>
                            <button type="button" wire:click="moveFieldDown({{ $index }})" @disabled($loop->last) class="flex size-7 items-center justify-center rounded-control text-slate hover:bg-surface hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 disabled:opacity-30" aria-label="Move {{ $field['label'] ?: 'field' }} down">
                                <x-ui.icon name="arrow-down" class="size-4" />
                            </button>
                            <button type="button" wire:click="toggleField({{ $index }})" class="flex size-7 items-center justify-center rounded-control hover:bg-surface hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 {{ $open ? 'text-navy-700' : 'text-slate' }}" aria-label="{{ $open ? 'Close' : 'Edit' }} {{ $field['label'] ?: 'field' }}">
                                <x-ui.icon :name="$open ? 'chevron-down' : 'pencil'" class="size-4" />
                            </button>
                            <button type="button" wire:click="removeField({{ $index }})" class="flex size-7 items-center justify-center rounded-control text-slate hover:bg-surface hover:text-crimson-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson-600" aria-label="Remove {{ $field['label'] ?: 'field' }}">
                                <x-ui.icon name="trash" class="size-4" />
                            </button>
                        </div>
                        @endunless
                    </div>

                    @if ($open)
                        <div class="space-y-3 px-2 pb-3 sm:pl-13">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_11rem]">
                                <x-ui.input compact name="fields.{{ $index }}.label" label="Label" wire:model.live.debounce.500ms="fields.{{ $index }}.label" required placeholder="Certificate of Registration" />
                                <x-ui.select compact name="fields.{{ $index }}.field_type" label="Type" wire:model.live="fields.{{ $index }}.field_type">
                                    @foreach (\App\Enums\FieldType::cases() as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <x-ui.input compact name="fields.{{ $index }}.description" label="Helper text" wire:model.live.debounce.500ms="fields.{{ $index }}.description" placeholder="Shown under the field on the form" />

                            @if ($fieldType->needsOptions())
                                <x-ui.textarea compact name="fields.{{ $index }}.options" label="Choices" wire:model.live.debounce.500ms="fields.{{ $index }}.options" rows="3" hint="One choice per line.">{{ $field['options'] }}</x-ui.textarea>
                            @endif

                            @if ($fieldType->isFile())
                                <div class="grid grid-cols-1 gap-3 rounded-control border border-line bg-surface p-3 sm:grid-cols-2">
                                    <x-ui.input compact name="fields.{{ $index }}.accepted_formats" label="Accepted formats" wire:model.live.debounce.500ms="fields.{{ $index }}.accepted_formats" hint="Comma-separated, e.g. pdf,jpg,png" />
                                    <x-ui.input compact name="fields.{{ $index }}.max_size_mb" label="Max file size (MB)" type="number" min="1" wire:model.live.debounce.500ms="fields.{{ $index }}.max_size_mb" />
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-control border px-2.5 py-1.5 font-body text-[13px] text-ink transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-navy-600 {{ $field['is_required'] ? 'border-navy-600 bg-navy-50' : 'border-line bg-surface hover:border-navy-600' }}">
                                    <input type="checkbox" wire:model.live="fields.{{ $index }}.is_required" class="rounded border-line text-navy-600 focus-visible:ring-navy-600">
                                    Required
                                </label>

                                @if ($fieldType->isFile())
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-control border px-2.5 py-1.5 font-body text-[13px] text-ink transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-navy-600 {{ $field['requires_physical_copy'] ? 'border-navy-600 bg-navy-50' : 'border-line bg-surface hover:border-navy-600' }}">
                                        <input type="checkbox" wire:model.live="fields.{{ $index }}.requires_physical_copy" class="rounded border-line text-navy-600 focus-visible:ring-navy-600">
                                        <x-ui.icon name="building" class="size-4 text-navy-700" />
                                        Also requires a physical copy
                                    </label>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="flex flex-col items-center rounded-control border border-dashed border-line px-4 py-8 text-center">
                    <span class="flex size-10 items-center justify-center rounded-full bg-navy-50 text-navy-700">
                        <x-ui.icon name="form" class="size-4" />
                    </span>
                    <p class="mt-3 font-body text-[14px] text-slate">No form fields yet.</p>
                    @unless ($readonly)
                    <button type="button" wire:click="addField" class="mt-2 inline-flex items-center gap-1 font-body text-[14px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                        <x-ui.icon name="plus" class="size-4" /> Add a field
                    </button>
                    @endunless
                </div>
            @endforelse

            @if (! empty($fields) && ! $readonly)
                <button type="button" wire:click="addField" wire:loading.attr="disabled" wire:target="addField" class="mt-1 inline-flex items-center gap-1 rounded-control px-2.5 py-1.5 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                    <x-ui.icon name="plus" class="size-4" /> Add field
                </button>
            @endif
        </div>
    </div>

    <aside class="space-y-4 lg:sticky lg:top-20">
        <div class="overflow-hidden rounded-card border border-line bg-surface">
            <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                    <x-ui.icon name="lock" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-display text-[15px] font-600 text-ink">System fields</h3>
                    <p class="font-body text-[12px] text-mist">Collected on every application.</p>
                </div>
            </div>
            <ul class="grid grid-cols-2 gap-x-3 gap-y-2 px-4 py-3">
                @foreach ($systemFields as $systemField)
                    <li class="flex items-center gap-2 font-body text-[13px] text-ink">
                        <x-ui.icon :name="$systemField['icon']" class="size-4 text-slate" />
                        {{ $systemField['label'] }}
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($isAcademic)
            <div class="overflow-hidden rounded-card border border-line bg-surface">
                <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-600">
                        <x-ui.icon name="award" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-display text-[15px] font-600 text-ink">Academic standard</h3>
                        <p class="font-body text-[12px] text-mist">Applicants below it are screened out.</p>
                    </div>
                </div>
                <div class="px-4 py-3">
                    <x-ui.input
                        name="required_gwa"
                        label="Required GWA"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        wire:model.live.debounce.500ms="required_gwa"
                        required
                        hint="Minimum GWA as a percentage, e.g. 90."
                    />
                </div>
            </div>
        @endif
    </aside>
</div>
