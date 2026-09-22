<?php

use App\Livewire\Concerns\ConfiguresApplicationForm;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Requirements'))] class extends Component
{
    use ConfiguresApplicationForm;

    public ProgramOffering $offering;

    public function mount(ProgramOffering $offering): void
    {
        $this->offering = $offering->load('scholarshipProgram', 'academicTerm');
        $this->bootConfiguration();
    }

    protected function configurationOwner(): Model
    {
        return $this->offering;
    }

    protected function owningProgram(): ScholarshipProgram
    {
        return $this->offering->scholarshipProgram;
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.scholarship-programs.offerings', $offering->scholarshipProgram)" label="{{ $offering->scholarshipProgram->name }} offerings" />

    <h1 class="mt-3 font-display text-[22px] font-600 text-ink sm:text-[26px] lg:text-[30px]">{{ $offering->displayName() }} requirements</h1>

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
