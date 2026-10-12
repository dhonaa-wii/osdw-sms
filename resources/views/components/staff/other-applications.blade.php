@props(['applications'])

{{-- The applicant's other applications and awards, so staff can see multiple applications while reviewing. --}}
@if ($applications->isNotEmpty())
    <x-staff.review-card icon="layers" title="Other applications">
        <x-slot:meta>
            <span class="font-mono text-[12px] text-mist">{{ $applications->count() }}</span>
        </x-slot:meta>

        <ul class="divide-y divide-line">
            @foreach ($applications as $other)
                @php $badge = $other->outcomeBadge(); @endphp
                <li class="flex items-start justify-between gap-3 px-4 py-2.5" wire:key="other-application-{{ $other->id }}">
                    <div class="min-w-0">
                        <p class="truncate font-body text-[13px] font-500 text-ink">{{ $other->programOffering->scholarshipProgram->name }}</p>
                        <p class="truncate font-body text-[12px] text-mist">{{ $other->programOffering->displayName() }} &middot; {{ $other->statusLabel() }}</p>
                    </div>
                    <span class="mt-0.5 shrink-0 border-l-2 border-{{ $badge['color'] }} pl-2 font-body text-[10px] font-600 uppercase tracking-[0.08em] text-{{ $badge['color'] }}">{{ $badge['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </x-staff.review-card>
@endif
