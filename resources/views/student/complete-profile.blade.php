<?php

use App\Livewire\Concerns\EditsStudentProfile;
use App\Services\AccountService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shown once, right after a student creates their account. Their student ID
 * is the key every application is filed under, so it is set here and cannot
 * be changed afterwards.
 */
new #[Layout('layouts::public', array('title' => 'Set up your profile', 'noindex' => true))] class extends Component
{
    use EditsStudentProfile;

    public string $student_id = '';

    public function mount(): void
    {
        if (auth()->user()->applicant) {
            $this->redirectRoute('student.applications.index', navigate: true);
        }
    }

    public function save(AccountService $accounts): void
    {
        $this->student_id = trim($this->student_id);

        $this->validate(
            [
                'student_id' => ['required', 'string', 'max:30', 'unique:applicants,student_id'],
                ...$this->profileRules(),
            ],
            ['student_id.unique' => 'This Student ID is already registered to another account. If it is yours, contact the OSDW office.'],
        );

        try {
            $accounts->completeProfile(auth()->user(), [...$this->profileData(), 'student_id' => $this->student_id]);
        } catch (\RuntimeException $e) {
            $this->addError('student_id', $e->getMessage());

            return;
        }

        session()->flash('toast', 'Your profile is saved.');
        $this->redirectIntended(default: route('student.applications.index'), navigate: true);
    }
};
?>

<x-auth.card title="Set up your profile" width="max-w-2xl">
    <form wire:submit="save" novalidate class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.input name="student_id" label="Student ID" wire:model="student_id" required autofocus class="font-mono" hint="Check it carefully. You can't change it later." />
            <div>
                <p class="block font-body text-[13px] font-500 text-ink">Email</p>
                <p class="mt-1 flex h-[34px] items-center break-all font-body text-[14px] text-slate">{{ auth()->user()->email }}</p>
            </div>
        </div>

        <x-student.profile-fields class="border-t border-line pt-5" />

        <div class="flex justify-end border-t border-line pt-4">
            <x-ui.button type="submit" loading="save">Save profile</x-ui.button>
        </div>
    </form>
</x-auth.card>
