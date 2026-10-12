<?php

use App\Models\Campus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Campus'))] class extends Component
{
    public ?int $campusId = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    public function mount(): void
    {
        $campus = Campus::first();

        if ($campus) {
            $this->campusId = $campus->id;
            $this->name = $campus->name;
        }
    }

    public function save(): void
    {
        $validated = $this->validate();

        $campus = Campus::updateOrCreate(['id' => $this->campusId], $validated);

        $this->campusId = $campus->id;
        $this->dispatch('notice', title: 'Campus updated');
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <h1 class="mt-3 font-display text-[30px] font-600 text-ink">Campus</h1>

    <form wire:submit="save" class="mt-8 max-w-sm space-y-5">
        <x-ui.input name="name" label="Campus name" wire:model="name" required maxlength="100" />

        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save</x-ui.button>
    </form>
</div>
