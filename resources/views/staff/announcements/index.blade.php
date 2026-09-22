<?php

use App\Models\Announcement;
use App\Models\ProgramOffering;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Announcements'))] class extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:200')]
    public string $title = '';

    #[Validate('required|string')]
    public string $body = '';

    public string $program_offering_id = '';

    public bool $editingIsPublished = false;

    public ?string $successMessage = null;

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'body', 'program_offering_id']);
        $this->editingIsPublished = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $announcement = Announcement::findOrFail($id);

        $this->editingId = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->program_offering_id = (string) ($announcement->program_offering_id ?? '');
        $this->editingIsPublished = $announcement->is_published;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(bool $publish = false): void
    {
        $validated = $this->validate();
        $validated['program_offering_id'] = $this->program_offering_id ?: null;

        if ($this->editingId) {
            $announcement = Announcement::findOrFail($this->editingId);

            if ($publish && ! $announcement->is_published) {
                $validated['is_published'] = true;
                $validated['published_at'] = now();
            }

            $announcement->update($validated);
            $this->successMessage = $publish ? 'Announcement published.' : 'Announcement updated.';
        } else {
            if ($publish) {
                $validated['is_published'] = true;
                $validated['published_at'] = now();
            }

            Announcement::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
            $this->successMessage = $publish ? 'Announcement published.' : 'Announcement created as a draft. Publish it to make it visible.';
        }

        $this->showModal = false;
        $this->resetPage();
    }

    public function togglePublish(int $id): void
    {
        $announcement = Announcement::findOrFail($id);

        $announcement->update([
            'is_published' => ! $announcement->is_published,
            'published_at' => ! $announcement->is_published ? now() : $announcement->published_at,
        ]);

        $this->successMessage = $announcement->is_published ? 'Announcement published.' : 'Announcement unpublished.';
    }

    public function with(): array
    {
        return [
            'announcements' => Announcement::query()
                ->with('programOffering.scholarshipProgram')
                ->orderByDesc('created_at')
                ->paginate(10),
            'offerings' => ProgramOffering::with('scholarshipProgram', 'academicTerm')
                ->orderByDesc('closes_at')
                ->get(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-[30px] font-600 text-ink">Announcements</h1>
        </div>
        <x-ui.button wire:click="create">Add announcement</x-ui.button>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    <div class="mt-6">
        @if ($announcements->isEmpty())
            <x-ui.empty-state title="No announcements yet" message="Post a general notice or one tied to a specific scholarship program.">
                <x-slot:action>
                    <x-ui.button wire:click="create">Add announcement</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="space-y-3">
                @foreach ($announcements as $announcement)
                    <div class="rounded-card border border-line bg-surface p-4" wire:key="announcement-{{ $announcement->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-display text-[16px] font-600 text-ink">{{ $announcement->title }}</p>
                                <p class="mt-1 font-body text-[13px] text-slate">
                                    {{ $announcement->programOffering?->scholarshipProgram->name ?? 'General' }}
                                    @if ($announcement->is_published)
                                        &middot; <span class="text-forest-700">Published {{ $announcement->published_at?->format('M j, Y') }}</span>
                                    @else
                                        &middot; <span class="text-mist">Draft</span>
                                    @endif
                                </p>
                                <p class="mt-2 font-body text-[14px] text-ink">{{ \Illuminate\Support\Str::limit($announcement->body, 160) }}</p>
                            </div>
                            <div class="flex shrink-0 gap-3">
                                <button type="button" wire:click="edit({{ $announcement->id }})" class="font-body text-[13px] text-forest-700 hover:underline">Edit</button>
                                <button type="button" wire:click="togglePublish({{ $announcement->id }})" class="font-body text-[13px] text-slate hover:underline">
                                    {{ $announcement->is_published ? 'Unpublish' : 'Publish' }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($announcements->hasPages())
                <div class="mt-6">{{ $announcements->links() }}</div>
            @endif
        @endif
    </div>

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit announcement' : 'Add announcement'">
        <form wire:submit="save" class="space-y-5">
            <x-ui.input name="title" label="Title" wire:model="title" required maxlength="200" />

            <x-ui.select name="program_offering_id" label="Applies to" wire:model="program_offering_id" placeholder="General (all applicants)">
                @foreach ($offerings as $offering)
                    <option value="{{ $offering->id }}">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.textarea name="body" label="Message" wire:model="body" rows="4" required />

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                @if ($editingIsPublished)
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save announcement</x-ui.button>
                @else
                    <x-ui.button variant="secondary" type="button" wire:click="save(false)" wire:loading.attr="disabled" wire:target="save">Save as draft</x-ui.button>
                    <x-ui.button type="button" wire:click="save(true)" wire:loading.attr="disabled" wire:target="save">Publish</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.modal>
</div>
