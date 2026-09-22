<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::staff', array('title' => 'Users'))] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('required|email|max:150')]
    public string $email = '';

    public string $password = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password']);
        $this->resetValidation();
        $this->errorMessage = null;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::where('role', UserRole::OsdwStaff)->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->resetValidation();
        $this->errorMessage = null;
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email,'.($this->editingId ?? 'NULL').',id',
        ];

        if (! $this->editingId) {
            $rules['password'] = 'required|string|min:8';
        }

        $validated = $this->validate($rules);

        if ($this->editingId) {
            User::where('role', UserRole::OsdwStaff)->findOrFail($this->editingId)->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $this->successMessage = 'User updated.';
        } else {
            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => UserRole::OsdwStaff,
                'password' => $validated['password'],
                'email_verified_at' => now(),
            ]);

            $this->successMessage = 'User created.';
        }

        $this->showModal = false;
        $this->resetPage();
    }

    public function resetPassword(int $id): void
    {
        $user = User::where('role', UserRole::OsdwStaff)->findOrFail($id);
        $newPassword = Str::random(10);

        $user->update(['password' => $newPassword]);

        $this->successMessage = "Password reset for {$user->name}. New temporary password: {$newPassword}";
    }

    public function toggleActive(int $id): void
    {
        $user = User::where('role', UserRole::OsdwStaff)->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);

        $this->errorMessage = null;
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->where('role', UserRole::OsdwStaff)
                ->when($this->search, fn ($query) => $query
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"))
                ->orderBy($this->sortBy, $this->sortDirection)
                ->paginate(10),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application Settings" />

    <div class="mt-3 flex items-center justify-between">
        <div>
            <h1 class="font-display text-[30px] font-600 text-ink">Users</h1>
            <p class="mt-1 font-body text-[15px] text-slate">OSDW Staff accounts. The Administrator account is managed outside this screen.</p>
        </div>
        <x-ui.button wire:click="create">Add user</x-ui.button>
    </div>

    @if ($successMessage)
        <x-ui.alert type="success" class="mt-6">{{ $successMessage }}</x-ui.alert>
    @endif

    @if ($errorMessage)
        <x-ui.alert type="danger" class="mt-6">{{ $errorMessage }}</x-ui.alert>
    @endif

    <div class="mt-6 max-w-sm">
        <x-ui.input name="search" wire:model.live.debounce.300ms="search" placeholder="Search by name or email" />
    </div>

    <div class="mt-4">
        @if ($users->isEmpty())
            <x-ui.empty-state title="No staff accounts yet" message="OSDW Staff accounts appear here once added.">
                <x-slot:action>
                    <x-ui.button wire:click="create">Add user</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.data-table
                :headers="[
                    ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                    ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                    ['key' => 'is_active', 'label' => 'Status', 'sortable' => false],
                    ['key' => 'actions', 'label' => '', 'sortable' => false],
                ]"
                :rows="$users"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
            >
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-paper">
                        <td data-label="Name" class="text-[14px] text-ink">{{ $user->name }}</td>
                        <td data-label="Email" class="font-mono text-[13px] text-slate">{{ $user->email }}</td>
                        <td data-label="Status"><x-ui.active-stamp :active="$user->is_active" /></td>
                        <td data-label="Actions" class="space-x-3 text-[14px]">
                            <button type="button" wire:click="edit({{ $user->id }})" class="text-forest-700 hover:underline">Edit</button>
                            <button type="button" wire:click="resetPassword({{ $user->id }})" wire:confirm="Generate a new temporary password for {{ $user->name }}?" class="text-slate hover:underline">Reset password</button>
                            <button type="button" wire:click="toggleActive({{ $user->id }})" class="text-slate hover:underline">
                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>

    <x-ui.modal :show="$showModal" :title="$editingId ? 'Edit user' : 'Add user'">
        <form wire:submit="save" class="space-y-5">
            <x-ui.input name="name" label="Name" wire:model="name" required maxlength="150" />
            <x-ui.input name="email" label="Email" type="email" wire:model="email" required maxlength="150" />

            @if (! $editingId)
                <x-ui.input name="password" label="Temporary password" type="password" wire:model="password" required hint="Share this with the user directly. They are not emailed automatically." />
            @endif

            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Save user</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
