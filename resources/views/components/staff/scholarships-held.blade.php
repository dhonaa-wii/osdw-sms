@props(['awards', 'blocker' => null])

{{--
    The scholarships a student currently holds in other programs
    (ScholarshipHoldingService::heldAwards()), with the one-national rule's
    blocker when it applies to the application being reviewed.
--}}
@if ($awards->isNotEmpty())
    <x-staff.review-card icon="award" title="Scholarships held" {{ $attributes }}>
        <x-slot:meta>
            <span class="font-mono text-[12px] text-mist">{{ $awards->count() }}</span>
        </x-slot:meta>

        @if ($blocker)
            <p class="flex items-start gap-2 border-b border-line bg-crimson-600/5 px-4 py-2.5 font-body text-[13px] text-crimson-600">
                <x-ui.icon name="alert-circle" class="mt-px size-4 shrink-0" />
                <span>{{ $blocker }}</span>
            </p>
        @endif

        <ul class="divide-y divide-line">
            @foreach ($awards as $held)
                @php
                    $offering = $held->programOffering;
                    $source = $offering->scholarshipProgram->provider?->funding_source;
                @endphp
                <li class="flex items-start gap-3 px-4 py-2.5" wire:key="held-award-{{ $held->id }}">
                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-gold-600/10 text-gold-600">
                        <x-ui.icon name="award" class="size-3.5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-body text-[13px] font-500 text-ink">{{ $offering->scholarshipProgram->name }}</p>
                        <p class="truncate font-body text-[12px] text-mist">{{ $offering->displayName() }} &middot; {{ $offering->academicTerm->school_year }} &middot; {{ $offering->academicTerm->semester->label() }}</p>
                    </div>
                    @if ($source)
                        <span class="mt-0.5 shrink-0 border-l-2 pl-2 font-body text-[10px] font-600 uppercase tracking-[0.08em] {{ $source->isNational() ? 'border-gold-600 text-gold-600' : 'border-azure-600 text-azure-600' }}" title="{{ $source->label() }}">{{ $source->label() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-staff.review-card>
@endif
