<?php

use App\Models\Applicant;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Recover Reference Number'))] class extends Component
{
    public string $student_id = '';

    public string $last_name = '';

    public string $birthdate = '';

    public bool $searched = false;

    public array $references = [];

    public function submit(): void
    {
        $this->validate([
            'student_id' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'birthdate' => ['required', 'date'],
        ]);

        $this->searched = true;

        $applicant = Applicant::where('student_id', $this->student_id)
            ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($this->last_name))])
            ->whereDate('birthdate', $this->birthdate)
            ->first();

        $this->references = $applicant
            ? $applicant->applications()->with('programOffering.scholarshipProgram')->get()
                ->map(fn ($application) => [
                    'program' => $application->programOffering->scholarshipProgram->name,
                    'reference_no' => $application->reference_no,
                ])
                ->all()
            : [];
    }
};
?>

<div class="mx-auto max-w-md">
    <x-ui.back-link :href="route('track.lookup')" label="Track your application" />

    <div class="mt-4 text-center">
        <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-forest-50 text-forest-700">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <path d="m4 7 8 6 8-6" />
            </svg>
        </span>
        <h1 class="mt-3 font-display text-[28px] font-600 text-ink">Recover your reference number</h1>
    </div>

    <div class="mt-8 rounded-card border border-line bg-surface p-6">
        <form wire:submit="submit" class="space-y-5">
            <x-ui.input name="student_id" label="Student ID" wire:model="student_id" required />
            <x-ui.input name="last_name" label="Last name" wire:model="last_name" required />
            <x-ui.input name="birthdate" label="Birthdate" type="date" wire:model="birthdate" required />
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit" class="w-full">Find my reference numbers</x-ui.button>
        </form>
    </div>

    @if ($searched)
        <div class="mt-6">
            @if (empty($references))
                <x-ui.alert type="warning">No applications matched those details.</x-ui.alert>
            @else
                <x-ui.alert type="success">
                    <ul class="space-y-1">
                        @foreach ($references as $reference)
                            <li>{{ $reference['program'] }} &mdash; <span class="font-mono">{{ $reference['reference_no'] }}</span></li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif
        </div>
    @endif
</div>
