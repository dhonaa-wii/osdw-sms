<?php

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'System Settings'))] class extends Component
{
    public string $max_upload_size_mb = '10';

    public bool $public_results_visible = true;

    public ?string $successMessage = null;

    protected array $keys = [
        'max_upload_size_mb',
        'public_results_visible',
    ];

    public function mount(): void
    {
        $settings = Setting::whereIn('key', $this->keys)->pluck('value', 'key');

        foreach ($this->keys as $key) {
            if (isset($settings[$key])) {
                $this->{$key} = $key === 'public_results_visible' ? (bool) $settings[$key] : $settings[$key];
            }
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'max_upload_size_mb' => ['required', 'integer', 'min:1'],
        ]);

        $descriptions = [
            'max_upload_size_mb' => 'Suggested ceiling for document upload size when configuring offering requirements.',
            'public_results_visible' => 'Controls whether the public Results page is reachable at all.',
        ];

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'description' => $descriptions[$key]]);
        }

        Setting::updateOrCreate(
            ['key' => 'public_results_visible'],
            ['value' => $this->public_results_visible ? '1' : '0', 'description' => $descriptions['public_results_visible']]
        );

        $this->successMessage = 'Settings saved.';
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application settings" />

    <h1 class="mt-3 font-display text-[30px] font-600 text-ink">System settings</h1>
    <p class="mt-1 font-body text-[13px] text-mist">There is no limit on how many programs a student may apply to — the system flags multiple applications to staff rather than capping them, and that isn't configurable here.</p>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    <form wire:submit="save" class="mt-8 max-w-lg space-y-5">
        <x-ui.input
            name="max_upload_size_mb"
            label="Suggested max upload size (MB)"
            type="number"
            min="1"
            required
            wire:model="max_upload_size_mb"
            hint="Shown to staff when they set a document requirement's size limit."
        />

        <label class="flex items-center gap-2 font-body text-[14px] text-ink">
            <input type="checkbox" wire:model="public_results_visible" class="rounded-control border-line focus-visible:ring-2 focus-visible:ring-forest-600">
            Public Results page is reachable
        </label>

        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save settings</x-ui.button>
    </form>
</div>
