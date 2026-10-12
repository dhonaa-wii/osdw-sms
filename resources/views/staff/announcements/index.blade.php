<?php

use App\Models\Announcement;
use App\Models\ProgramOffering;
use App\Services\StudentNotificationService;
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

    public string $search = '';

    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

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
            $firstPublish = $publish && $announcement->published_at === null;

            if ($publish && ! $announcement->is_published) {
                $validated['is_published'] = true;
                $validated['published_at'] = now();
            }

            $announcement->update($validated);

            if ($firstPublish) {
                app(StudentNotificationService::class)->announcementPublished($announcement);
            }
            $this->dispatch('notice', title: $publish ? 'Announcement published' : 'Announcement updated');
        } else {
            if ($publish) {
                $validated['is_published'] = true;
                $validated['published_at'] = now();
            }

            $announcement = Announcement::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);

            if ($publish) {
                app(StudentNotificationService::class)->announcementPublished($announcement);
            }
            $this->dispatch('notice', title: $publish ? 'Announcement published' : 'Draft saved', message: $publish ? '' : 'Publish it to make it visible.');

            // A new announcement lands at the top of the first page.
            $this->resetPage();
        }

        $this->showModal = false;
    }

    public function togglePublish(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $firstPublish = $announcement->published_at === null;

        $announcement->update([
            'is_published' => ! $announcement->is_published,
            'published_at' => ! $announcement->is_published ? now() : $announcement->published_at,
        ]);

        // Students hear about an announcement once, not again each time it is re-published.
        if ($announcement->is_published && $firstPublish) {
            app(StudentNotificationService::class)->announcementPublished($announcement);
        }

        $this->dispatch('notice', title: $announcement->is_published ? 'Announcement published' : 'Announcement unpublished');
    }

    public function with(): array
    {
        return [
            'announcements' => Announcement::query()
                ->with('programOffering.scholarshipProgram')
                ->when(trim($this->search) !== '', fn ($query) => $query->where('title', 'like', '%'.trim($this->search).'%'))
                ->when($this->status === 'published', fn ($query) => $query->where('is_published', true))
                ->when($this->status === 'draft', fn ($query) => $query->where('is_published', false))
                ->orderByDesc('created_at')
                ->paginate(10),
            'counts' => [
                'all' => Announcement::count(),
                'published' => Announcement::where('is_published', true)->count(),
                'draft' => Announcement::where('is_published', false)->count(),
            ],
            'offerings' => ProgramOffering::with('scholarshipProgram', 'academicTerm')
                ->orderByDesc('closes_at')
                ->get(),
        ];
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Announcements</h1>
        <x-ui.button x-on:click="$wire.showModal = true; $wire.create()">
            <x-ui.icon name="plus" class="size-4" />
            Add announcement
        </x-ui.button>
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search announcements"
                placeholder="Search by title"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <x-ui.segmented
                class="md:ml-auto"
                label="Filter announcements"
                model="status"
                :value="$status"
                :options="['all' => 'All', 'published' => 'Published', 'draft' => 'Drafts']"
                :counts="$counts"
            />
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="search, status, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($announcements->isEmpty())
                @if (trim($search) !== '')
                    <x-ui.list-empty icon="search" title="No announcements match" message="Try another title.">
                        <x-ui.button variant="secondary" wire:click="$set('search', '')">Clear search</x-ui.button>
                    </x-ui.list-empty>
                @elseif ($counts['all'] === 0)
                    <x-ui.list-empty icon="megaphone" title="No announcements yet" message="Post a general notice or one tied to a specific scholarship offering.">
                        <x-ui.button x-on:click="$wire.showModal = true; $wire.create()">Add announcement</x-ui.button>
                    </x-ui.list-empty>
                @else
                    <x-ui.list-empty icon="megaphone" :title="$status === 'draft' ? 'No drafts' : 'Nothing published yet'" message="The other filters hold the rest.">
                        <x-ui.button variant="secondary" wire:click="$set('status', 'all')">Show all announcements</x-ui.button>
                    </x-ui.list-empty>
                @endif
            @else
                <x-ui.list-table>
                    <x-slot:head>
                        <th>Announcement</th>
                        <th class="md:w-px md:whitespace-nowrap">Applies to</th>
                        <th class="md:w-px md:whitespace-nowrap">Status</th>
                        <th class="md:w-px"><span class="sr-only">Actions</span></th>
                    </x-slot:head>

                    @foreach ($announcements as $announcement)
                        @php $offering = $announcement->programOffering; @endphp
                        <tr
                            wire:key="announcement-{{ $announcement->id }}"
                            class="group cursor-pointer transition-colors hover:bg-paper/60"
                            x-on:click="if (! window.getSelection().toString()) { $wire.showModal = true; $wire.edit({{ $announcement->id }}) }"
                        >
                            <td class="col-start-1 min-w-0 md:max-w-0">
                                <p class="truncate font-body text-[14px] font-500 text-ink group-hover:text-navy-700">{{ $announcement->title }}</p>
                                <p class="truncate font-body text-[12px] text-mist">{{ \Illuminate\Support\Str::limit($announcement->body, 160) }}</p>
                            </td>
                            <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap">
                                <span class="inline-flex max-w-64 items-center gap-1.5" @if ($offering) title="{{ $offering->scholarshipProgram->name }} · {{ $offering->displayName() }}" @endif>
                                    <x-ui.icon :name="$offering ? 'award' : 'users'" class="size-3.5 text-mist" />
                                    <span class="truncate">{{ $offering ? $offering->scholarshipProgram->name.' · '.$offering->displayName() : 'General' }}</span>
                                </span>
                            </td>
                            <td class="col-start-1 md:whitespace-nowrap">
                                @if ($announcement->is_published)
                                    <span class="border-l-2 border-navy-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-navy-700">Published</span>
                                    <span class="ml-1 font-mono text-[12px] text-mist">{{ $announcement->published_at?->format('M j, Y') }}</span>
                                @else
                                    <span class="border-l-2 border-line pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Draft</span>
                                @endif
                            </td>
                            <td class="col-start-2 row-span-3 row-start-1 self-center" x-on:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.icon-button icon="pencil" label="Edit {{ $announcement->title }}" x-on:click="$wire.showModal = true; $wire.edit({{ $announcement->id }})" />
                                    @if ($announcement->is_published)
                                        <x-ui.icon-button
                                            icon="eye"
                                            label="Unpublish {{ $announcement->title }}"
                                            loading="togglePublish({{ $announcement->id }})"
                                            x-on:click="$store.confirm.ask({
                                                title: 'Unpublish announcement?',
                                                message: 'It comes off the public site. You can publish it again later.',
                                                confirmLabel: 'Unpublish announcement',
                                                action: () => $wire.togglePublish({{ $announcement->id }}),
                                            })"
                                        />
                                    @else
                                        <x-ui.icon-button icon="megaphone" tone="primary" label="Publish {{ $announcement->title }}" wire:click="togglePublish({{ $announcement->id }})" loading="togglePublish({{ $announcement->id }})" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.list-table>

                @if ($announcements->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $announcements->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Add / edit form. Visibility lives in Alpine so it opens instantly and survives re-renders. --}}
    <x-ui.dialog
        show="$wire.showModal"
        close="$wire.showModal = false"
        size="lg"
        loading="create, edit"
        :title="$editingId ? 'Edit announcement' : 'Add announcement'"
    >
        <form wire:submit="save" class="space-y-4">
            <x-ui.input name="title" label="Title" wire:model="title" required maxlength="200" />

            <x-ui.select name="program_offering_id" label="Applies to" wire:model="program_offering_id" placeholder="General (all applicants)">
                @foreach ($offerings as $offering)
                    <option value="{{ $offering->id }}">{{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.textarea name="body" label="Message" wire:model="body" rows="5" required />

            <div class="flex justify-end gap-2 border-t border-line pt-3">
                <x-ui.button variant="secondary" class="px-3 py-1.5 text-[13px]" x-on:click="$wire.showModal = false">Cancel</x-ui.button>
                @if ($editingIsPublished)
                    <x-ui.button type="submit" class="px-3 py-1.5 text-[13px]" loading="save">Save announcement</x-ui.button>
                @else
                    <x-ui.button variant="secondary" class="px-3 py-1.5 text-[13px]" wire:click="save(false)" loading="save(false)">Save as draft</x-ui.button>
                    <x-ui.button class="px-3 py-1.5 text-[13px]" wire:click="save(true)" loading="save(true)">
                        <x-ui.icon name="megaphone" class="size-3.5" />
                        Publish announcement
                    </x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.dialog>
</div>
