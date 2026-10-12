<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AccountService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Users'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('required|email|max:150')]
    public string $email = '';

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
        $this->reset(['editingId', 'name', 'email']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::where('role', UserRole::OsdwStaff)->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(AccountService $accounts): void
    {
        $rules = [
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email,'.($this->editingId ?? 'NULL').',id',
        ];

        $validated = $this->validate($rules);

        if ($this->editingId) {
            User::where('role', UserRole::OsdwStaff)->findOrFail($this->editingId)->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $this->dispatch('notice', title: 'User updated');
        } else {
            [$user, $password] = $accounts->createStaff($validated['name'], $validated['email']);

            $this->dispatch('notice', title: 'User created', message: $this->credentialsMessage($user, $password));
            $this->resetPage();
        }

        $this->showModal = false;
    }

    public function resetPassword(int $id, AccountService $accounts): void
    {
        $user = User::where('role', UserRole::OsdwStaff)->findOrFail($id);
        $password = $accounts->resetStaffPassword($user);

        $this->dispatch('notice', title: 'Password reset', message: $this->credentialsMessage($user, $password));
    }

    /**
     * Shown once; the password is never displayed again.
     */
    protected function credentialsMessage(User $user, string $password): string
    {
        return "Give these to {$user->name}. Email: {$user->email} · Temporary password: {$password} — they set their own password and connect Google at their first log-in.";
    }

    public function toggleActive(int $id): void
    {
        $user = User::where('role', UserRole::OsdwStaff)->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function with(): array
    {
        $staff = User::query()->where('role', UserRole::OsdwStaff);

        return [
            'users' => (clone $staff)
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('email', 'like', '%'.trim($this->search).'%')))
                ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('name')
                ->paginate(10),
            'counts' => [
                'all' => (clone $staff)->count(),
                'active' => (clone $staff)->where('is_active', true)->count(),
                'inactive' => (clone $staff)->where('is_active', false)->count(),
            ],
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application settings" wire:navigate />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[26px] font-600 text-ink">Staff users</h1>
        <x-ui.button x-on:click="$wire.showModal = true; $wire.create()">
            <x-ui.icon name="plus" class="size-4" />
            Add user
        </x-ui.button>
    </div>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.search
                name="search"
                label="Search users"
                placeholder="Search by name or email"
                wire:model.live.debounce.300ms="search"
                class="md:w-72"
            />

            <x-ui.segmented
                class="md:ml-auto"
                label="Filter users"
                model="status"
                :value="$status"
                :options="['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']"
                :counts="$counts"
            />
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="search, status, gotoPage, nextPage, previousPage" class="transition-opacity">
            @if ($users->isEmpty())
                @if (trim($search) !== '')
                    <x-ui.list-empty icon="search" title="No users match" message="Try another name or email.">
                        <x-ui.button variant="secondary" wire:click="$set('search', '')">Clear search</x-ui.button>
                    </x-ui.list-empty>
                @elseif ($counts['all'] === 0)
                    <x-ui.list-empty icon="users" title="No staff accounts yet" message="Add an account for each OSDW staff member.">
                        <x-ui.button x-on:click="$wire.showModal = true; $wire.create()">Add user</x-ui.button>
                    </x-ui.list-empty>
                @else
                    <x-ui.list-empty icon="users" :title="$status === 'active' ? 'No active users' : 'No inactive users'" message="The other filters hold the rest.">
                        <x-ui.button variant="secondary" wire:click="$set('status', 'all')">Show all users</x-ui.button>
                    </x-ui.list-empty>
                @endif
            @else
                <x-ui.list-table>
                    <x-slot:head>
                        <th>Name</th>
                        <th>Email</th>
                        <th class="md:w-px md:whitespace-nowrap">Status</th>
                        <th class="md:w-px"><span class="sr-only">Actions</span></th>
                    </x-slot:head>

                    @foreach ($users as $user)
                        @php
                            $initials = mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join(''));
                        @endphp
                        <tr
                            wire:key="user-{{ $user->id }}"
                            class="group cursor-pointer transition-colors hover:bg-paper/60"
                            x-on:click="if (! window.getSelection().toString()) { $wire.showModal = true; $wire.edit({{ $user->id }}) }"
                        >
                            <td class="col-start-1 min-w-0">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full font-display text-[12px] font-600 {{ $user->is_active ? 'bg-navy-50 text-navy-700' : 'bg-paper text-mist' }}" aria-hidden="true">{{ $initials }}</span>
                                    <span class="truncate font-body text-[14px] font-500 group-hover:text-navy-700 {{ $user->is_active ? 'text-ink' : 'text-slate' }}">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="col-start-1 min-w-0 font-body text-[13px] text-slate">
                                <span class="inline-flex min-w-0 max-w-full items-center gap-1.5">
                                    <x-ui.icon name="mail" class="size-3.5 text-mist" />
                                    <span class="truncate">{{ $user->email }}</span>
                                </span>
                            </td>
                            <td class="col-start-1 md:whitespace-nowrap">
                                @if ($user->is_active && $user->needsAccountSetup())
                                    <span class="border-l-2 border-amber-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Setup pending</span>
                                @else
                                    <x-ui.active-stamp :active="$user->is_active" />
                                @endif
                            </td>
                            <td class="col-start-2 row-span-3 row-start-1 self-center" x-on:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.icon-button icon="pencil" label="Edit {{ $user->name }}" x-on:click="$wire.showModal = true; $wire.edit({{ $user->id }})" />
                                    <x-ui.icon-button
                                        icon="lock"
                                        label="Reset password for {{ $user->name }}"
                                        loading="resetPassword({{ $user->id }})"
                                        x-on:click="$store.confirm.ask({ title: {{ \Illuminate\Support\Js::from('Reset password for '.$user->name.'?') }}, message: 'A new temporary password is generated and shown once. They must change it at their next log-in.', confirmLabel: 'Reset password', tone: 'primary', action: () => $wire.resetPassword({{ $user->id }}) })"
                                    />
                                    @if ($user->is_active)
                                        <x-ui.icon-button
                                            icon="power"
                                            tone="danger"
                                            label="Deactivate {{ $user->name }}"
                                            loading="toggleActive({{ $user->id }})"
                                            x-on:click="$store.confirm.ask({ title: {{ \Illuminate\Support\Js::from('Deactivate '.$user->name.'?') }}, message: 'They can’t sign in until you activate the account again.', confirmLabel: 'Deactivate user', action: () => $wire.toggleActive({{ $user->id }}) })"
                                        />
                                    @else
                                        <x-ui.icon-button icon="power" tone="primary" label="Activate {{ $user->name }}" wire:click="toggleActive({{ $user->id }})" loading="toggleActive({{ $user->id }})" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.list-table>

                @if ($users->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $users->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Add / edit form. Visibility lives in Alpine so it opens instantly and survives re-renders. --}}
    <x-ui.dialog
        show="$wire.showModal"
        close="$wire.showModal = false"
        loading="create, edit"
        :title="$editingId ? 'Edit user' : 'Add user'"
    >
        <form wire:submit="save" class="space-y-4">
            <x-ui.input name="name" label="Name" wire:model="name" required maxlength="150" />
            <x-ui.input name="email" label="Email" type="email" wire:model="email" required maxlength="150" />

            @if (! $editingId)
                <p class="font-body text-[13px] text-slate">A temporary password is generated and shown once after you add the user.</p>
            @endif


            <div class="flex justify-end gap-2 border-t border-line pt-3">
                <x-ui.button variant="secondary" class="px-3 py-1.5 text-[13px]" x-on:click="$wire.showModal = false">Cancel</x-ui.button>
                <x-ui.button type="submit" class="px-3 py-1.5 text-[13px]" loading="save">{{ $editingId ? 'Save user' : 'Add user' }}</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
