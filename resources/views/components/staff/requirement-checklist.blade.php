@props(['stage', 'rows', 'readonly' => false, 'bare' => false])

{{--
    One stage of an application's additional requirements, as staff verify
    them — a section of the review page's Documents card. Unless readonly,
    the host Livewire component provides verifyRequirement(), rejectRequirement()
    (returning true once returned) and togglePhysicalRequirement().
    Pass bare inside a modal to sit flush with its edges.
--}}
@php
    $compliedCount = $rows->where('complied', true)->count();
    $total = $rows->count();
    $stageIcons = ['shortlist' => 'shortlist', 'award' => 'award', 'renewal' => 'clipboard-check'];
@endphp

<x-staff.document-group
    :title="$stage->reviewHeading()"
    :icon="$stageIcons[$stage->value]"
    :complied="$compliedCount"
    :total="$total"
    :class="$bare ? '-mx-4 -mb-3.5 border-t' : ''"
    wire:key="checklist-{{ $stage->value }}"
>
        @foreach ($rows as $row)
            @php
                $requirement = $row['requirement'];
                $submission = $row['submission'];
                $mode = $requirement->submission_mode;
                $hasFile = (bool) $submission?->file_path;
                $pending = ! $readonly && $hasFile && $submission->status->value === 'pending';
            @endphp

            @if ($hasFile)
                <x-staff.document-item
                    :url="route('staff.applications.additional-requirements.show', $submission)"
                    :name="$submission->original_name"
                    :mime="$submission->mime_type"
                    :label="$requirement->label"
                    wire:key="requirement-file-{{ $submission->id }}-{{ crc32($submission->file_path) }}"
                    x-data="{ returning: false, remarks: '' }"
                >
                    <x-slot:meta>
                        <span class="shrink-0 {{ match ($submission->status->value) { 'verified' => 'text-navy-700', 'rejected' => 'text-crimson-600', default => 'text-amber-600' } }}">
                            {{ match ($submission->status->value) { 'verified' => 'Verified', 'rejected' => 'Returned', default => 'Awaiting review' } }}
                        </span>
                        <span class="text-mist">&middot;</span>
                        <span class="truncate text-mist">{{ $submission->status->value === 'rejected' && $submission->remarks ? $submission->remarks : $mode->label() }}</span>
                    </x-slot:meta>

                    <x-slot:actions>
                        @if ($mode->needsPhysical())
                            <x-staff.physical-copy-toggle :received="(bool) $submission->physical_received_at" :received-at="$submission->physical_received_at" :action="$readonly ? null : 'togglePhysicalRequirement('.$requirement->id.')'" />
                        @endif
                        @if ($pending)
                            <x-ui.button variant="secondary" class="h-8 px-3 py-0 text-[13px] text-crimson-600" x-on:click="returning = ! returning; $nextTick(() => $refs.reason?.focus())">
                                <x-ui.icon name="x" class="size-3.5" /> Return
                            </x-ui.button>
                            <x-ui.button class="h-8 px-3 py-0 text-[13px]" wire:click="verifyRequirement({{ $submission->id }})" loading="verifyRequirement({{ $submission->id }})">
                                <x-ui.icon name="check" class="size-3.5" /> Verify
                            </x-ui.button>
                        @endif
                    </x-slot:actions>

                    @if ($pending)
                        <x-staff.reason-form
                            show="returning"
                            submit="if (await $wire.rejectRequirement({{ $submission->id }}, remarks)) { returning = false; remarks = ''; }"
                            :id="'return-remarks-'.$submission->id"
                            :label="'Reason for returning '.$requirement->label"
                            placeholder="What needs to be fixed"
                            cancel="returning = false"
                            loading="rejectRequirement"
                        >Return upload</x-staff.reason-form>
                    @endif
                </x-staff.document-item>
            @else
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2.5 px-4 py-3.5" wire:key="requirement-row-{{ $requirement->id }}">
                    <div class="flex min-w-0 flex-1 basis-60 items-center gap-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-control border {{ $row['complied'] ? 'border-navy-600/30 bg-navy-50 text-navy-700' : 'border-line bg-paper text-mist' }}">
                            <x-ui.icon :name="$row['complied'] ? 'check-circle' : 'clock'" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="truncate font-body text-[14px] font-600 text-ink">{{ $requirement->label }}</h3>
                            <p class="flex min-w-0 items-center gap-1.5 font-body text-[12px] text-mist">
                                <span>{{ $mode->label() }}</span>
                                @if ($mode->needsUpload())
                                    <span>&middot;</span>
                                    <span>Not uploaded yet</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @if ($mode->needsPhysical())
                        <div class="flex shrink-0 justify-end max-sm:w-full">
                            <x-staff.physical-copy-toggle :received="(bool) $submission?->physical_received_at" :received-at="$submission?->physical_received_at" :action="$readonly ? null : 'togglePhysicalRequirement('.$requirement->id.')'" />
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
</x-staff.document-group>
