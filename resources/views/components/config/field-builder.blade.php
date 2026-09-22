@props(['fields', 'isAcademic' => false])

<div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
    <div class="space-y-3 lg:w-[90%]">
        @error('fields')
            <x-ui.alert type="error">{{ $message }}</x-ui.alert>
        @enderror

        @forelse ($fields as $index => $field)
            @php $fieldType = \App\Enums\FieldType::from($field['field_type']); @endphp
            <div class="rounded-card border border-line bg-surface p-3 sm:p-3.5" wire:key="field-row-{{ $index }}">
                <div class="flex items-start gap-2.5">
                    <div class="flex flex-col gap-1 pt-1.5">
                        <button type="button" wire:click="moveFieldUp({{ $index }})" @if ($index === 0) disabled @endif class="text-slate hover:text-forest-700 disabled:opacity-30" title="Move up">&uarr;</button>
                        <button type="button" wire:click="moveFieldDown({{ $index }})" @if ($index === count($fields) - 1) disabled @endif class="text-slate hover:text-forest-700 disabled:opacity-30" title="Move down">&darr;</button>
                    </div>

                    <div class="min-w-0 flex-1 space-y-2.5">
                        <div class="flex flex-col gap-2.5 sm:flex-row">
                            <div class="flex-1">
                                <x-ui.input compact name="fields.{{ $index }}.label" label="Label" wire:model.live.debounce.500ms="fields.{{ $index }}.label" required placeholder="Certificate of Registration" />
                            </div>
                            <div class="w-full sm:w-40">
                                <x-ui.select compact name="fields.{{ $index }}.field_type" label="Type" wire:model.live="fields.{{ $index }}.field_type">
                                    @foreach (\App\Enums\FieldType::cases() as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        </div>

                        <x-ui.input compact name="fields.{{ $index }}.description" label="Helper text" wire:model.live.debounce.500ms="fields.{{ $index }}.description" placeholder="Optional instructions shown under the field" />

                        @if ($fieldType->needsOptions())
                            <x-ui.textarea compact name="fields.{{ $index }}.options" label="Choices" wire:model.live.debounce.500ms="fields.{{ $index }}.options" rows="3" hint="One choice per line, e.g. Below 10,000 / 10,000-20,000 / Above 20,000.">{{ $field['options'] }}</x-ui.textarea>
                        @endif

                        @if ($fieldType->isFile())
                            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                <x-ui.input compact name="fields.{{ $index }}.accepted_formats" label="Accepted formats" wire:model.live.debounce.500ms="fields.{{ $index }}.accepted_formats" hint="Comma-separated, e.g. pdf,jpg,png" />
                                <x-ui.input compact name="fields.{{ $index }}.max_size_mb" label="Max file size (MB)" type="number" min="1" wire:model.live.debounce.500ms="fields.{{ $index }}.max_size_mb" />
                            </div>
                            <label class="flex items-center gap-2 font-body text-[12px] text-ink sm:text-[13px]">
                                <input type="checkbox" wire:model.live="fields.{{ $index }}.requires_physical_copy" class="rounded border-line text-forest-600 focus-visible:ring-forest-600">
                                Also requires a physical copy
                            </label>
                        @endif

                        <label class="flex items-center gap-2 font-body text-[12px] text-ink sm:text-[13px]">
                            <input type="checkbox" wire:model.live="fields.{{ $index }}.is_required" class="rounded border-line text-forest-600 focus-visible:ring-forest-600">
                            Required
                        </label>
                    </div>

                    <button type="button" wire:click="removeField({{ $index }})" class="text-[13px] text-crimson-600 hover:underline" title="Remove field">Remove</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state title="No form fields yet" message="The application form is empty until you add a field.">
                <x-slot:action>
                    <x-ui.button type="button" wire:click="addField">Add field</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse

        @if (! empty($fields))
            <button type="button" wire:click="addField" class="font-body text-[14px] font-500 text-forest-700 hover:underline">+ Add field</button>
        @endif
    </div>

    <div class="space-y-6 lg:sticky lg:top-6">
        <div class="rounded-card border border-line bg-paper p-4 sm:p-5 lg:p-6">
            <p class="font-body text-[10px] font-600 uppercase tracking-[0.08em] text-mist sm:text-[11px]">System fields</p>
            <p class="mt-1 font-body text-[12px] text-slate sm:text-[13px]">Collected on every application &mdash; no need to add these again below.</p>

            <ul class="mt-4 space-y-1.5 font-body text-[12px] text-ink sm:text-[13px]">
                <li>Student ID</li>
                <li>Name</li>
                <li>Sex</li>
                <li>Birthdate</li>
                <li>Email</li>
                <li>Mobile number</li>
                <li>Address</li>
                <li>College</li>
                <li>Year level</li>
            </ul>

            @if ($isAcademic)
                <div class="mt-4 border-t border-line pt-4">
                    <x-ui.input
                        name="required_gwa"
                        label="Required GWA"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        wire:model.live.debounce.500ms="required_gwa"
                        required
                        hint="Minimum GWA as a percentage, e.g. 90. Applicants below this GWA are screened out."
                    />
                </div>
            @endif
        </div>

        <div class="rounded-card border border-line bg-paper p-4 sm:p-5 lg:p-6">
            <p class="font-body text-[10px] font-600 uppercase tracking-[0.08em] text-mist sm:text-[11px]">New fields</p>

            @if (empty($fields))
                <p class="mt-3 font-body text-[13px] text-mist sm:text-[14px]">Add a field to see it listed here.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($fields as $field)
                        @php $listedType = \App\Enums\FieldType::from($field['field_type']); @endphp
                        <li class="flex items-center justify-between gap-2 font-body text-[12px] text-ink sm:text-[13px]">
                            <span class="truncate">{{ $field['label'] ?: 'Untitled field' }}</span>
                            <span class="shrink-0 text-mist">{{ $listedType->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <button type="button" wire:click="addField" class="mt-4 font-body text-[12px] font-500 text-forest-700 hover:underline sm:text-[13px]">+ Add field</button>
        </div>
    </div>
</div>
