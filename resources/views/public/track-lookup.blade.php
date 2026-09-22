<?php

use App\Models\Applicant;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public', array('title' => 'Track Your Application'))] class extends Component
{
    public string $student_id = '';

    public string $reference_no = '';

    public function submit(): void
    {
        $this->validate([
            'student_id' => ['required', 'string'],
            'reference_no' => ['required', 'string'],
        ]);

        $applicant = Applicant::where('student_id', $this->student_id)->first();
        $matches = $applicant
            && $applicant->applications()->where('reference_no', strtoupper(trim($this->reference_no)))->exists();

        if (! $matches) {
            $this->addError('reference_no', 'No application matches that Student ID and reference number.');

            return;
        }

        session(['tracking_applicant_id' => $applicant->id]);

        $this->redirect(route('track.dashboard'));
    }
};
?>

<div class="mx-auto max-w-md">
    <div class="text-center">
        <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-forest-50 text-forest-700">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.3-4.3" />
            </svg>
        </span>
        <h1 class="mt-3 font-display text-[28px] font-600 text-ink">Track your application</h1>
    </div>

    <div class="mt-8 rounded-card border border-line bg-surface p-6">
        <form wire:submit="submit" class="space-y-5">
            <x-ui.input name="student_id" label="Student ID" wire:model="student_id" required />
            <x-ui.input name="reference_no" label="Reference number" wire:model="reference_no" required class="font-mono uppercase" placeholder="SCH-2026-A7K9M2" />
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit" class="w-full">Track application</x-ui.button>
        </form>
    </div>

    <div class="mt-6 flex flex-col items-center gap-1 text-center">
        <p class="font-body text-[14px] text-slate">
            Forgot your reference number? <a href="{{ route('track.recover') }}" class="text-forest-700 hover:underline">Recover it</a>.
        </p>
        <p class="font-body text-[14px] text-slate">
            Haven't applied yet? <a href="{{ route('programs.index') }}" class="text-forest-700 hover:underline">Browse open scholarships</a>.
        </p>
    </div>
</div>
