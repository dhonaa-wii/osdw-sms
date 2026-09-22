<?php

use App\Livewire\Concerns\ConfiguresApplicationForm;
use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Requirements'))] class extends Component
{
    use ConfiguresApplicationForm;

    public ScholarshipProgram $scholarshipProgram;

    public function mount(ScholarshipProgram $scholarshipProgram): void
    {
        $this->scholarshipProgram = $scholarshipProgram;
        $this->bootConfiguration();
    }

    protected function configurationOwner(): Model
    {
        return $this->scholarshipProgram;
    }

    protected function owningProgram(): ScholarshipProgram
    {
        return $this->scholarshipProgram;
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.scholarship-programs')" label="Scholarship programs" />

    <h1 class="mt-3 font-display text-[22px] font-600 text-ink sm:text-[26px] lg:text-[30px]">{{ $scholarshipProgram->name }} requirements</h1>

    <div class="mt-6">
        <div class="flex flex-wrap justify-end gap-3">
            <x-ui.button type="button" variant="secondary" wire:click="$set('showPreview', true)">View form</x-ui.button>
            <x-ui.button type="button" wire:click="saveFields" wire:loading.attr="disabled" wire:target="saveFields">Save requirements</x-ui.button>
        </div>

        @if ($fieldsSuccessMessage)
            <x-ui.alert type="success" class="mt-4">{{ $fieldsSuccessMessage }}</x-ui.alert>
        @endif

        <x-config.field-builder :fields="$fields" :is-academic="$this->isAcademic()" />
    </div>

    <x-config.form-preview
        :fields="$fields"
        :show="$showPreview"
        :is-academic="$this->isAcademic()"
        :requires-income="$this->requiresIncome()"
    />
</div>
