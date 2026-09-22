<?php

use App\Models\Award;
use App\Models\ProgramOffering;
use App\Services\AwardService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Awards'))] class extends Component
{
    public ProgramOffering $offering;

    public ?int $forfeitingAwardId = null;

    public string $forfeitReason = '';

    public ?string $successMessage = null;

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm', 'offeringColleges.college');
    }

    public function togglePublishResults(): void
    {
        $this->offering->update(['results_published' => ! $this->offering->results_published]);
        $this->successMessage = $this->offering->results_published ? 'Results published to the public page.' : 'Results unpublished.';
    }

    public function startForfeit(int $awardId): void
    {
        $this->forfeitingAwardId = $awardId;
        $this->forfeitReason = '';
        $this->resetValidation();
    }

    public function confirmForfeit(): void
    {
        if (trim($this->forfeitReason) === '') {
            $this->addError('forfeitReason', 'A reason is required.');

            return;
        }

        app(AwardService::class)->forfeit(Award::findOrFail($this->forfeitingAwardId), auth()->user(), $this->forfeitReason);

        $this->forfeitingAwardId = null;
        $this->successMessage = 'Award forfeited.';
    }

    public function with(): array
    {
        $service = app(AwardService::class);

        $awards = $this->offering->awards()
            ->with('application.applicant', 'application.college')
            ->where('status', '!=', 'terminated')
            ->orderByDesc('awarded_at')
            ->get();

        $slotCounters = collect();

        if ($this->offering->allocation_mode->value === 'quota') {
            foreach ($this->offering->offeringColleges as $offeringCollege) {
                $slotCounters->push([
                    'label' => $offeringCollege->college->name,
                    'used' => $service->usedSlots($this->offering, $offeringCollege->college_id),
                    'total' => $offeringCollege->slots,
                ]);
            }
        } else {
            $slotCounters->push(['label' => 'Overall', 'used' => $service->usedSlots($this->offering), 'total' => $this->offering->total_slots]);
        }

        return [
            'awards' => $awards,
            'slotCounters' => $slotCounters,
            'totalApplicants' => $this->offering->applications()->count(),
            'awardedCount' => $awards->count(),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('staff.applications.workspace', $offering)" label="{{ $offering->scholarshipProgram->name }} applications" />

    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-[28px] font-600 text-ink">Awards &mdash; {{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>
        </div>
        <div class="text-right">
            <x-ui.button variant="secondary" wire:click="togglePublishResults">
                {{ $offering->results_published ? 'Unpublish results' : 'Publish results' }}
            </x-ui.button>
            <p class="mt-1 font-body text-[12px] text-slate">
                @if ($offering->results_published)
                    Visible on the public results page.
                @else
                    Not visible to the public yet.
                @endif
            </p>
        </div>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-4">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Total applicants</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">{{ $totalApplicants }}</p>
            <p class="mt-1 font-body text-[12px] text-slate">All applications for this offering, including rejected and not-yet-awarded.</p>
        </div>
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Awarded</p>
            <p class="mt-1 font-mono text-[20px] leading-tight text-ink">{{ $awardedCount }}</p>
            <p class="mt-1 font-body text-[12px] text-slate">Applicants who actually received an award.</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-{{ $slotCounters->count() > 1 ? 3 : 1 }}">
        @foreach ($slotCounters as $counter)
            @php $percent = $counter['total'] > 0 ? min(100, round($counter['used'] / $counter['total'] * 100)) : 0; @endphp
            <div class="rounded-card border border-line bg-surface p-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" /></svg>
                    </span>
                    <div>
                        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">{{ $counter['label'] }} slots</p>
                        <p class="font-mono text-[20px] leading-tight text-ink">{{ $counter['used'] }} / {{ $counter['total'] }}</p>
                    </div>
                </div>
                <div class="mt-3 h-1.5 rounded-full bg-paper">
                    <div class="h-1.5 rounded-full {{ $percent >= 100 ? 'bg-crimson-600' : 'bg-gold-600' }}" style="width: {{ $percent }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Awarded</p>
        <p class="mt-1 font-body text-[13px] text-slate">Grantees recorded for this offering. Endorse and award applicants from the offering's Shortlist tab.</p>

        @if ($awards->isEmpty())
            <x-ui.empty-state title="No awards yet" message="Award a shortlisted applicant from the Shortlist tab to populate this list." class="mt-3" />
        @else
            <div class="mt-3">
                <x-ui.data-table :headers="[
                    ['key' => 'applicant', 'label' => 'Grantee', 'sortable' => false],
                    ['key' => 'college', 'label' => 'College', 'sortable' => false],
                    ['key' => 'gwa', 'label' => 'GWA', 'sortable' => false],
                    ['key' => 'amount', 'label' => 'Amount', 'sortable' => false],
                    ['key' => 'contact', 'label' => 'Contact', 'sortable' => false],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]" :rows="$awards">
                    @foreach ($awards as $award)
                        @php $applicant = $award->application->applicant; @endphp
                        <tr wire:key="award-{{ $award->id }}" class="transition hover:bg-paper">
                            <td data-label="Grantee" class="text-[14px] text-ink">
                                {{ $applicant->last_name }}, {{ $applicant->first_name }}
                                <br><span class="font-mono text-[12px] text-slate">{{ $applicant->student_id }}</span>
                            </td>
                            <td data-label="College" class="text-[14px] text-slate">{{ $award->application->college->code }}</td>
                            <td data-label="GWA" class="font-mono text-[14px] text-ink">{{ $award->application->gwa ?? '&mdash;' }}</td>
                            <td data-label="Amount" class="font-mono text-[14px] text-ink">
                                @if ($award->amount)
                                    &#8369;{{ number_format($award->amount, 2) }}
                                @elseif ($award->discount_rate)
                                    {{ number_format($award->discount_rate, 2) }}%
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td data-label="Contact" class="text-[13px] text-slate">
                                {{ $applicant->email }}
                                <br><span class="font-mono text-[12px]">{{ $applicant->mobile ?? '&mdash;' }}</span>
                            </td>
                            <td data-label="Status" class="text-[14px]">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.status-stamp :status="$award->acceptance_status" />
                                    <x-ui.status-stamp :status="$award->status" />
                                </div>
                            </td>
                            <td data-label="Actions" class="text-[14px]">
                                @if ($forfeitingAwardId === $award->id)
                                    <div class="space-y-2">
                                        <x-ui.textarea name="forfeitReason" wire:model="forfeitReason" rows="2" placeholder="Reason for forfeiture (required)" />
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="$set('forfeitingAwardId', null)" class="font-body text-[13px] text-slate hover:underline">Cancel</button>
                                            <button type="button" wire:click="confirmForfeit" class="font-body text-[13px] font-500 text-crimson-600 hover:underline">Confirm forfeiture</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex flex-col items-start gap-1">
                                        <button type="button" wire:click="startForfeit({{ $award->id }})" class="font-body text-[13px] text-crimson-600 hover:underline">Forfeit</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </div>
        @endif
    </div>
</div>
