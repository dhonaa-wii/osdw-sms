@props(['application'])

{{-- Status history of an application, oldest first, ending with the disbursement once recorded. --}}
@php
    $logs = $application->statusLogs->sortBy('created_at');
    $disbursedAward = $application->isDisbursed() ? $application->award : null;
@endphp

<x-staff.review-card icon="clock" title="Timeline">
    @if ($logs->isEmpty())
        <p class="px-4 py-3.5 font-body text-[13px] text-mist">No status changes yet.</p>
    @else
        <ol class="relative space-y-3 px-4 py-3.5">
            @foreach ($logs as $log)
                @php $isCurrent = $loop->last && ! $disbursedAward; @endphp
                <li class="relative pl-5" wire:key="status-log-{{ $log->id }}">
                    <span class="absolute left-0 top-1.5 size-2 rounded-full {{ $isCurrent ? 'bg-navy-600' : 'bg-line' }}" aria-hidden="true"></span>
                    @unless ($isCurrent)
                        <span class="absolute left-[3.5px] top-4 h-[calc(100%+4px)] w-px bg-line" aria-hidden="true"></span>
                    @endunless
                    <p class="font-body text-[13px] font-500 text-ink">{{ $application->statusLabel($log->to_status) }}</p>
                    <p class="font-body text-[12px] text-mist">
                        <span class="font-mono">{{ $log->created_at->format('M j, Y g:ia') }}</span>
                        @if ($log->changedBy) &middot; {{ $log->changedBy->name }} @endif
                    </p>
                    @if ($log->reason)
                        <p class="mt-0.5 font-body text-[12px] text-slate">&ldquo;{{ $log->reason }}&rdquo;</p>
                    @endif
                </li>
            @endforeach
            @if ($disbursedAward)
                <li class="relative pl-5" wire:key="status-log-disbursed">
                    <span class="absolute left-0 top-1.5 size-2 rounded-full bg-navy-600" aria-hidden="true"></span>
                    <p class="font-body text-[13px] font-500 text-ink">Disbursed</p>
                    <p class="font-body text-[12px] text-mist">
                        <span class="font-mono">{{ $disbursedAward->disbursed_at->format('M j, Y g:ia') }}</span>
                        @if ($disbursedAward->disbursedBy) &middot; {{ $disbursedAward->disbursedBy->name }} @endif
                    </p>
                </li>
            @endif
        </ol>
    @endif
</x-staff.review-card>
