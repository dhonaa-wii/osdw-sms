@props(['fields', 'show' => false, 'isAcademic' => false])

<x-ui.dialog show="$wire.showPreview" close="$wire.showPreview = false" title="Application form — student view" size="xl">
    <div class="space-y-5">
        <div>
            <h3 class="font-body text-[12px] font-600 text-slate">Your identity</h3>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.input name="preview_student_id" label="Student ID" disabled required />
                <x-ui.select name="preview_sex" label="Sex" disabled required>
                    @foreach (\App\Enums\Sex::cases() as $option)
                        <option>{{ $option->label() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="preview_last_name" label="Last name" disabled required />
                <x-ui.input name="preview_first_name" label="First name" disabled required />
                <x-ui.input name="preview_middle_name" label="Middle name" disabled />
                <x-ui.input name="preview_suffix" label="Suffix" disabled placeholder="Jr., III, etc." />
                <x-ui.input name="preview_birthdate" label="Birthdate" type="date" disabled />
                <x-ui.input name="preview_email" label="Email" type="email" disabled required />
                <x-ui.input name="preview_mobile" label="Mobile number" disabled />
                <x-ui.textarea name="preview_address" label="Address" disabled rows="2" />
            </div>
        </div>

        <div class="border-t border-line pt-4">
            <h3 class="font-body text-[12px] font-600 text-slate">Academic standing</h3>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-ui.select name="preview_college" label="College" disabled required>
                    <option>Select your college</option>
                </x-ui.select>
                <x-ui.select name="preview_year_level" label="Year level" disabled required>
                    @foreach (range(1, 6) as $level)
                        <option>Year {{ $level }}</option>
                    @endforeach
                </x-ui.select>

                @if ($isAcademic)
                    <x-ui.input name="preview_gwa" label="GWA" type="number" disabled required hint="Enter your GWA as a percentage, e.g. 90." />
                @endif
                <x-ui.input name="preview_income" label="Family monthly income" type="number" disabled required hint="Your family's total monthly income in pesos." />
            </div>
        </div>

        <div class="border-t border-line pt-4">
            <h3 class="font-body text-[12px] font-600 text-slate">Program requirements</h3>

            @if (empty($fields))
                <p class="mt-3 font-body text-[14px] text-slate">No extra requirements.</p>
            @else
                <div class="mt-2 space-y-3">
                    @foreach ($fields as $field)
                        @php
                            $previewType = \App\Enums\FieldType::from($field['field_type']);
                            $previewChoices = $previewType->needsOptions()
                                ? array_filter(array_map('trim', explode("\n", $field['options'] ?? '')))
                                : [];
                        @endphp
                        <div>
                            <label class="block font-body text-[13px] font-500 text-ink">
                                {{ $field['label'] ?: 'Untitled field' }}
                                @if ($field['is_required'])<span class="text-crimson-600">*</span>@endif
                            </label>
                            @if ($field['description'])
                                <p class="font-body text-[12px] text-slate">{{ $field['description'] }}</p>
                            @endif

                            <div class="mt-1">
                                @switch($previewType)
                                    @case(\App\Enums\FieldType::Textarea)
                                        <textarea rows="3" disabled class="block w-full rounded-control border border-line bg-paper px-3 py-1.5 text-[14px] text-mist"></textarea>
                                        @break
                                    @case(\App\Enums\FieldType::Select)
                                        <select disabled class="block w-full rounded-control border border-line bg-paper px-3 py-1.5 text-[14px] text-mist">
                                            <option>Select</option>
                                            @foreach ($previewChoices as $choice)
                                                <option>{{ $choice }}</option>
                                            @endforeach
                                        </select>
                                        @break
                                    @case(\App\Enums\FieldType::Radio)
                                        <div class="space-y-1">
                                            @forelse ($previewChoices as $choice)
                                                <label class="flex items-center gap-2 font-body text-[13px] text-ink">
                                                    <input type="radio" disabled> {{ $choice }}
                                                </label>
                                            @empty
                                                <p class="font-body text-[13px] text-mist">Add choices above to preview.</p>
                                            @endforelse
                                        </div>
                                        @break
                                    @case(\App\Enums\FieldType::Checkbox)
                                        <label class="flex items-center gap-2 font-body text-[13px] text-ink">
                                            <input type="checkbox" disabled> Yes
                                        </label>
                                        @break
                                    @case(\App\Enums\FieldType::File)
                                    @case(\App\Enums\FieldType::Image)
                                        <div class="rounded-control border border-dashed border-line bg-paper px-3 py-4 text-center font-body text-[13px] text-mist">
                                            {{ strtoupper($field['accepted_formats'] ?: 'FILE') }} &middot; max {{ $field['max_size_mb'] ?: '—' }}MB
                                        </div>
                                        @break
                                    @default
                                        <input type="{{ $previewType->value }}" disabled class="block w-full rounded-control border border-line bg-paper px-3 py-1.5 text-[14px] text-mist">
                                @endswitch
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="mt-5 flex justify-end border-t border-line pt-4">
        <x-ui.button type="button" variant="secondary" x-on:click="$wire.showPreview = false">Close preview</x-ui.button>
    </div>
</x-ui.dialog>
