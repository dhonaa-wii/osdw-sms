<?php

use App\Enums\OfferingStatus;
use App\Models\ProgramOffering;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Scholarship Details'))] class extends Component
{
    public ProgramOffering $offering;

    public function mount(ProgramOffering $offering): void
    {
        abort_unless($offering->registrationStatus() === OfferingStatus::Open, 404);

        $this->offering = $offering->load([
            'scholarshipProgram.provider',
            'academicTerm',
            'eligibilityRules',
            'offeringColleges.college',
            'formFields' => fn ($query) => $query->orderBy('sort_order'),
            'announcements' => fn ($query) => $query->where('is_published', true)->orderByDesc('published_at'),
        ]);
    }

    public function isCollegeRestricted(): bool
    {
        return $this->offering->college_scope->value === 'restricted' || $this->offering->allocation_mode->value === 'quota';
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-ui.back-link :href="route('programs.index')" label="Open scholarships" />
        <a href="{{ route('track.lookup') }}" class="font-body text-[13px] text-slate transition-colors hover:text-forest-700">Already applied? Track your application &rarr;</a>
    </div>

    @php $closingSoon = $offering->closes_at <= now()->addDays(7); @endphp

    <div class="mt-4">
        <p class="font-body text-[13px] font-600 uppercase tracking-[0.08em] text-forest-700">
            {{ $offering->scholarshipProgram->provider->name }} &middot; {{ $offering->scholarshipProgram->provider->funding_source->label() }} &middot; {{ $offering->scholarshipProgram->category->label() }}
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="font-display text-[28px] font-600 text-ink">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>
            @if ($closingSoon)
                <span class="border-l-2 border-amber-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Closing soon</span>
            @endif
        </div>
    </div>

    @if ($offering->scholarshipProgram->description)
        <p class="mt-4 font-body text-[15px] text-ink">{{ $offering->scholarshipProgram->description }}</p>
    @endif

    <div class="mt-6 grid grid-cols-2 divide-x divide-y divide-line rounded-card border border-line bg-surface sm:grid-cols-3 sm:divide-y-0" data-reveal>
        <div class="p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Slots</p>
            <p class="mt-1 font-mono text-[16px] text-ink">{{ $offering->total_slots }}</p>
        </div>
        <div class="p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Deadline</p>
            <p class="mt-1 font-mono text-[16px] text-ink">{{ $offering->closes_at->format('M j, Y') }}</p>
        </div>
        @if ($offering->amount)
            <div class="p-4">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Amount</p>
                <p class="mt-1 font-mono text-[16px] text-ink">&#8369;{{ number_format($offering->amount, 2) }}</p>
            </div>
        @endif
        @if ($offering->discount_rate)
            <div class="p-4">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Tuition discount</p>
                <p class="mt-1 font-mono text-[16px] text-ink">{{ $offering->discount_rate }}%</p>
            </div>
        @endif
    </div>

    @if ($this->isCollegeRestricted())
        <div class="mt-6" data-reveal>
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Open to</p>
            <p class="mt-1 font-body text-[14px] text-ink">{{ $offering->offeringColleges->pluck('college.name')->implode(', ') }}</p>
        </div>
    @endif

    @if ($offering->eligibilityRules->isNotEmpty())
        <div class="mt-6" data-reveal>
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Eligibility</p>
            <ul class="mt-2 space-y-1.5">
                @foreach ($offering->eligibilityRules as $rule)
                    <li class="flex items-start gap-2 font-body text-[14px] text-ink">
                        <svg class="mt-0.5 size-4 shrink-0 text-forest-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7" /></svg>
                        {{ $rule->describe() }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @php $requirements = $offering->formFields->where('field_type', \App\Enums\FieldType::File); @endphp
    @if ($requirements->isNotEmpty())
        <div class="mt-6" data-reveal>
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Documents you'll need</p>
            <ul class="mt-2 space-y-1.5">
                @foreach ($requirements as $requirement)
                    <li class="flex items-start gap-2 font-body text-[14px] text-ink">
                        <svg class="mt-0.5 size-4 shrink-0 text-slate" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M9 8h6M9 12h6" /></svg>
                        <span>
                            {{ $requirement->label }}
                            @if (! $requirement->is_required) <span class="text-mist">(optional)</span> @endif
                            <span class="font-mono text-[12px] text-slate">&mdash; {{ $requirement->accepted_formats }}, max {{ $requirement->max_size_mb }}MB</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($offering->announcements->isNotEmpty())
        <div class="mt-6" data-reveal>
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Announcements</p>
            <div class="mt-2 space-y-3">
                @foreach ($offering->announcements as $announcement)
                    <div class="rounded-card border border-line bg-surface p-4">
                        <p class="font-mono text-[12px] text-mist">{{ $announcement->published_at?->format('M j, Y') }}</p>
                        <p class="mt-1 font-display text-[15px] font-600 text-ink">{{ $announcement->title }}</p>
                        <p class="mt-1 font-body text-[14px] text-slate">{{ $announcement->body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-8 border-t border-line pt-6">
        <a href="{{ route('programs.apply', $offering) }}" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-5 py-3 font-body text-[15px] font-500 text-white transition hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Apply now</a>
        <p class="mt-2 font-body text-[13px] text-slate">Takes one sitting. You'll get a reference number immediately.</p>
    </div>
</div>
