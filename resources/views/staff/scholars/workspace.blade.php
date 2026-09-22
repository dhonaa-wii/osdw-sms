<?php

use App\Models\ProgramOffering;
use App\Models\ScholarTerm;
use App\Services\RetentionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Scholars'))] class extends Component
{
    public ProgramOffering $offering;

    public ?int $evaluatingTermId = null;

    public string $statusReason = '';

    public ?string $successMessage = null;

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm');
    }

    public function startStatus(int $scholarTermId): void
    {
        $this->evaluatingTermId = $scholarTermId;
        $this->statusReason = '';
        $this->resetValidation();
    }

    public function setStatus(string $status): void
    {
        if (in_array($status, ['probation', 'terminated'], true) && trim($this->statusReason) === '') {
            $this->addError('statusReason', 'A documented reason is required for this status.');

            return;
        }

        $term = ScholarTerm::findOrFail($this->evaluatingTermId);
        $term->update([
            'status' => $status,
            'remarks' => $this->statusReason ?: $term->remarks,
            'evaluated_by' => auth()->id(),
        ]);

        if ($status === 'terminated') {
            $term->award->update(['status' => 'terminated']);
        }

        $this->evaluatingTermId = null;
        $this->statusReason = '';
        $this->successMessage = 'Scholar status updated.';
    }

    public function with(): array
    {
        $service = app(RetentionService::class);

        $roster = $this->offering->awards()
            ->whereIn('status', ['active', 'reserved'])
            ->where('acceptance_status', 'accepted')
            ->with('applicant', 'scholarTerms')
            ->get()
            ->map(function ($award) use ($service) {
                $latestTerm = $award->scholarTerms->sortByDesc('created_at')->first();
                $evaluation = ($latestTerm && $latestTerm->gwa !== null) ? $service->evaluate($latestTerm) : collect();

                return [
                    'award' => $award,
                    'latestTerm' => $latestTerm,
                    'evaluation' => $evaluation,
                    'failedCount' => $evaluation->where('passed', false)->count(),
                ];
            });

        return [
            'roster' => $roster,
            'evaluatingRow' => $this->evaluatingTermId
                ? $roster->first(fn ($row) => $row['latestTerm']?->id === $this->evaluatingTermId)
                : null,
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('staff.applications.workspace', $offering)" label="{{ $offering->scholarshipProgram->name }} applications" />

    <h1 class="mt-3 font-display text-[28px] font-600 text-ink">Scholars &mdash; {{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-4">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6">
        @if ($roster->isEmpty())
            <x-ui.empty-state title="No active scholars yet" message="Scholars appear here once an award is accepted and marked compliant." />
        @else
            <x-ui.data-table :headers="[
                ['key' => 'student', 'label' => 'Student', 'sortable' => false],
                ['key' => 'status', 'label' => 'Status', 'sortable' => false],
                ['key' => 'gwa', 'label' => 'GWA', 'sortable' => false],
                ['key' => 'units', 'label' => 'Units', 'sortable' => false],
                ['key' => 'retention', 'label' => 'Retention', 'sortable' => false],
                ['key' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$roster">
                @foreach ($roster as $row)
                    @php [$award, $latestTerm, $failedCount] = [$row['award'], $row['latestTerm'], $row['failedCount']]; @endphp
                    <tr wire:key="scholar-{{ $award->id }}" class="transition hover:bg-paper">
                        <td data-label="Student" class="text-[14px] text-ink">
                            {{ $award->applicant->last_name }}, {{ $award->applicant->first_name }}
                            <br><span class="font-mono text-[12px] text-slate">{{ $award->applicant->student_id }}</span>
                        </td>
                        <td data-label="Status" class="text-[14px]">
                            @if ($latestTerm)
                                <span class="border-l-2 border-forest-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-forest-700">{{ $latestTerm->status->label() }}</span>
                            @else
                                <span class="border-l-2 border-mist pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">No grades</span>
                            @endif
                        </td>
                        <td data-label="GWA" class="font-mono text-[14px] text-ink">{{ $latestTerm?->gwa ?? '&mdash;' }}</td>
                        <td data-label="Units" class="font-mono text-[14px] text-ink">{{ $latestTerm?->units_enrolled ?? '&mdash;' }}</td>
                        <td data-label="Retention" class="text-[14px]">
                            @if (! $latestTerm)
                                <span class="font-body text-[13px] text-mist">&mdash;</span>
                            @elseif ($latestTerm->gwa === null)
                                <span class="font-body text-[13px] text-amber-600">Applied for renewal</span>
                            @elseif ($row['evaluation']->isEmpty())
                                <span class="font-body text-[13px] text-mist">Not configured</span>
                            @elseif ($failedCount > 0)
                                <span class="font-body text-[13px] text-crimson-600">{{ $failedCount }} rule(s) failed</span>
                            @else
                                <span class="font-body text-[13px] text-forest-700">Meets requirements</span>
                            @endif
                        </td>
                        <td data-label="Actions" class="text-[14px]">
                            @if ($latestTerm)
                                <button type="button" wire:click="startStatus({{ $latestTerm->id }})" class="font-body font-500 text-forest-700 hover:underline">Review</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>

    <x-ui.modal :show="(bool) $evaluatingRow" title="{{ $evaluatingRow['award']->applicant->last_name ?? '' }}, {{ $evaluatingRow['award']->applicant->first_name ?? '' }}">
        @if ($evaluatingRow)
            @php [$award, $latestTerm, $evaluation] = [$evaluatingRow['award'], $evaluatingRow['latestTerm'], $evaluatingRow['evaluation']]; @endphp

            <div class="flex flex-wrap gap-x-6 gap-y-1 font-body text-[13px] text-slate">
                <span>GWA: <span class="font-mono text-ink">{{ $latestTerm->gwa ?? '&mdash;' }}</span></span>
                <span>Units: <span class="font-mono text-ink">{{ $latestTerm->units_enrolled ?? '&mdash;' }}</span></span>
                <span>Failing grade: <span class="font-mono text-ink">{{ $latestTerm->has_failing_grade ? 'Yes' : 'No' }}</span></span>
            </div>

            @if ($evaluation->isNotEmpty())
                <div class="mt-3 space-y-1 border-t border-line pt-3">
                    @foreach ($evaluation as $result)
                        <p class="font-body text-[13px] {{ $result['passed'] ? 'text-slate' : 'text-crimson-600' }}">
                            {{ $result['passed'] ? '✓' : '✕' }} {{ $result['rule']->describe() }}
                            @if (! $result['passed']) &mdash; {{ $result['rule']->message }} @endif
                        </p>
                    @endforeach
                </div>
            @else
                <p class="mt-3 font-body text-[13px] text-mist border-t border-line pt-3">No retention rules configured for this offering &mdash; evaluate manually.</p>
            @endif

            <div class="mt-4 border-t border-line pt-4">
                <x-ui.textarea name="statusReason" wire:model="statusReason" rows="2" placeholder="Reason (required for probation or termination)" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" wire:click="setStatus('active')" class="rounded-control border border-line px-3 py-1.5 font-body text-[13px] hover:border-forest-600">Active</button>
                    <button type="button" wire:click="setStatus('probation')" class="rounded-control border border-line px-3 py-1.5 font-body text-[13px] hover:border-amber-600">Probation</button>
                    <button type="button" wire:click="setStatus('graduated')" class="rounded-control border border-line px-3 py-1.5 font-body text-[13px] hover:border-forest-600">Graduated</button>
                    <button type="button" wire:click="setStatus('terminated')" class="rounded-control border border-line px-3 py-1.5 font-body text-[13px] text-crimson-600 hover:border-crimson-600">Terminate</button>
                    <button type="button" wire:click="$set('evaluatingTermId', null)" class="font-body text-[13px] text-slate hover:underline">Cancel</button>
                </div>
            </div>
        @endif
    </x-ui.modal>
</div>
