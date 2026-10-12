<?php

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'System Settings'))] class extends Component
{
    public string $max_upload_size_mb = '10';

    public bool $public_results_visible = true;

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

        $this->dispatch('notice', title: 'Settings saved');
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application settings" wire:navigate />

    <h1 class="mt-3 font-display text-[26px] font-600 text-ink">System settings</h1>

    <form wire:submit="save" class="mt-5 max-w-2xl space-y-5">
        <x-staff.review-card icon="upload" title="Uploads">
            <div class="px-4 py-3.5">
                <div class="max-w-xs">
                    <x-ui.input
                        name="max_upload_size_mb"
                        label="Suggested max upload size (MB)"
                        type="number"
                        min="1"
                        required
                        wire:model="max_upload_size_mb"
                        hint="Shown to staff when they set a document's size limit."
                    />
                </div>
            </div>
        </x-staff.review-card>

        <x-staff.review-card icon="eye" title="Public pages">
            <label for="public_results_visible" class="flex cursor-pointer items-center gap-3 px-4 py-3.5">
                <span class="min-w-0 flex-1">
                    <span class="block font-body text-[14px] font-500 text-ink">Published results page</span>
                    <span class="block font-body text-[12px] text-mist">Turn off to hide every published awardee list from the public site.</span>
                </span>
                <span class="relative inline-flex shrink-0">
                    <input id="public_results_visible" type="checkbox" wire:model="public_results_visible" class="peer sr-only">
                    <span class="h-6 w-11 rounded-full bg-line transition-colors peer-checked:bg-navy-600 peer-focus-visible:ring-2 peer-focus-visible:ring-navy-600 peer-focus-visible:ring-offset-2"></span>
                    <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                </span>
            </label>
        </x-staff.review-card>

        <div class="flex justify-end">
            <x-ui.button type="submit" loading="save">Save settings</x-ui.button>
        </div>
    </form>
</div>
