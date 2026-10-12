@props(['chain', 'current', 'admin' => false, 'visible' => 4, 'hrefFor' => null, 'manage' => true])

{{--
    Tabs for an offering and every renewal offering that continues it
    (RenewalService::chainOf()). Past the newest few, older offerings fold
    behind "View all" — the current one always stays in view. Staff tabs open
    the offering's review page; admin tabs open its offering page. A host
    page can pass hrefFor to link the tabs elsewhere, and manage=false to
    leave out the Edit and Delete menu.

    For an administrator, each offering's tab carries a menu with
    Edit and Delete. The host Livewire component must use the
    DeletesOfferings trait.
--}}

@php
    $shown = $chain->slice(-$visible)->pluck('id')->push($current->id)->all();
    $hiddenCount = $chain->reject(fn ($member) => in_array($member->id, $shown, true))->count();
    $canManage = $manage && auth()->user()?->role === \App\Enums\UserRole::Admin;

    $hrefFor ??= fn ($member) => match (true) {
        $admin => route('admin.offerings.view', $member),
        $member->isRenewal() => route('staff.renewals.index', $member),
        default => route('staff.applications.workspace', $member),
    };
@endphp

<div {{ $attributes }}>
    <div x-data="{ showAll: false }" class="flex flex-wrap items-end gap-1">
        <nav class="-mb-px flex flex-wrap gap-1" aria-label="Offerings in this group">
            @foreach ($chain as $member)
                @php
                    $active = $member->id === $current->id;
                    $older = ! in_array($member->id, $shown, true);
                    $withMenu = $canManage;
                @endphp
                <div
                    wire:key="chain-{{ $member->id }}"
                    @if ($older) x-show="showAll" x-cloak @endif
                    class="relative flex items-start border-b-2 {{ $active ? 'border-navy-600' : 'border-transparent' }}"
                    @if ($withMenu) x-data="{ menu: false }" x-on:keydown.escape.window="menu = false" @endif
                >
                    <a
                        href="{{ $hrefFor($member) }}"
                        wire:navigate
                        @if ($active) aria-current="page" @endif
                        class="py-2 pl-4 {{ $withMenu ? 'pr-1' : 'pr-4' }} font-body text-[14px] font-500 transition-colors hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 {{ $active ? 'text-navy-700' : 'text-slate' }}"
                    >
                        {{ $member->displayName() }}
                        <span class="block font-body text-[12px] font-400 text-mist">
                            {{ $member->academicTerm->school_year }} &middot; {{ $member->academicTerm->semester->label() }}{{ $member->isRenewal() ? ' · Renewal' : '' }}
                        </span>
                    </a>

                    @if ($withMenu)
                        <button
                            type="button"
                            x-on:click="menu = ! menu"
                            x-bind:aria-expanded="menu.toString()"
                            aria-haspopup="menu"
                            aria-label="Actions for {{ $member->displayName() }}"
                            class="mr-1 mt-2 rounded-control p-1 text-slate transition-colors hover:bg-paper hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                        >
                            <x-ui.icon name="dots-vertical" class="size-4" />
                        </button>

                        <div
                            x-show="menu"
                            x-cloak
                            x-transition.opacity
                            x-on:click.outside="menu = false"
                            role="menu"
                            class="absolute right-0 top-full z-20 mt-1 w-40 overflow-hidden rounded-control border border-line bg-surface py-1 shadow-lg"
                        >
                            <a
                                href="{{ route('admin.scholarship-programs.offerings', ['scholarshipProgram' => $member->scholarship_program_id, 'edit' => $member->id]) }}"
                                wire:navigate
                                role="menuitem"
                                class="flex items-center gap-2 px-3 py-2 font-body text-[14px] text-ink hover:bg-paper focus-visible:bg-paper focus-visible:outline-none"
                            >
                                <x-ui.icon name="pencil" class="size-4 text-slate" />
                                Edit offering
                            </a>
                            <button
                                type="button"
                                role="menuitem"
                                x-on:click="menu = false; $store.confirm.ask({
                                    title: @js('Delete '.$member->displayName().'?'),
                                    message: @js('You can restore it from Settings → Deleted items within '.\App\Services\DeletionService::RETENTION_DAYS.' days.'),
                                    confirmLabel: 'Delete offering',
                                    action: () => $wire.deleteOffering({{ $member->id }}),
                                })"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left font-body text-[14px] text-crimson-600 hover:bg-paper focus-visible:bg-paper focus-visible:outline-none disabled:opacity-60"
                            >
                                <x-ui.icon name="trash" class="size-4" />
                                Delete offering
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </nav>

        @if ($hiddenCount > 0)
            <button
                type="button"
                x-on:click="showAll = ! showAll"
                x-bind:aria-expanded="showAll.toString()"
                class="mb-1.5 rounded-control px-3 py-1.5 font-body text-[13px] font-500 text-navy-700 hover:bg-navy-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
            >
                <span x-show="! showAll">View all <span class="font-mono">({{ $chain->count() }})</span></span>
                <span x-show="showAll" x-cloak>Show recent only</span>
            </button>
        @endif
    </div>
</div>
