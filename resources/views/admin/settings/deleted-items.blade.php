<?php

use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use App\Services\DeletionService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Deleted Items'))] class extends Component
{
    #[Url(except: 'programs')]
    public string $kind = 'programs';

    public function mount(DeletionService $deletionService): void
    {
        // The scheduler purges daily; this keeps the list honest without one.
        $deletionService->purgeExpired();
    }

    public function restoreProgram(int $id, DeletionService $deletionService): void
    {
        $program = ScholarshipProgram::onlyTrashed()->findOrFail($id);
        $deletionService->restoreProgram($program);

        $this->dispatch('notice', title: 'Scholarship program restored', message: $program->name);
    }

    public function restoreOffering(int $id, DeletionService $deletionService): void
    {
        $offering = ProgramOffering::onlyTrashed()->with('scholarshipProgram', 'academicTerm')->findOrFail($id);

        try {
            $deletionService->restoreOffering($offering);
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Offering not restored', message: $exception->getMessage());

            return;
        }

        $this->dispatch('notice', title: 'Offering restored', message: $offering->scholarshipProgram->name.' · '.$offering->displayName());
    }

    public function with(): array
    {
        // An offering deleted together with its program comes back with the
        // program, so only offerings deleted on their own are listed.
        $offerings = ProgramOffering::onlyTrashed()
            ->whereHas('scholarshipProgram')
            ->with('scholarshipProgram', 'academicTerm');

        $programs = ScholarshipProgram::onlyTrashed()
            ->with('provider')
            ->withCount(['offerings' => fn (Builder $query) => $query->onlyTrashed()->whereColumn('program_offerings.deleted_at', 'scholarship_programs.deleted_at')]);

        return [
            'programs' => (clone $programs)->latest('deleted_at')->get(),
            'offerings' => (clone $offerings)->latest('deleted_at')->get(),
            'counts' => [
                'programs' => (clone $programs)->count(),
                'offerings' => (clone $offerings)->count(),
            ],
        ];
    }
};
?>

@php
    $daysLeft = fn ($deletedAt) => max(0, (int) ceil(now()->diffInDays(app(\App\Services\DeletionService::class)->purgesOn($deletedAt), false)));
@endphp

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application settings" wire:navigate />

    <h1 class="mt-3 font-display text-[26px] font-600 text-ink">Deleted items</h1>

    <div class="mt-5 overflow-hidden rounded-card border border-line bg-surface">
        <div class="flex flex-col gap-3 border-b border-line p-3 md:flex-row md:items-center">
            <x-ui.segmented
                class="md:ml-auto"
                model="kind"
                :value="$kind"
                label="Show deleted"
                :options="['programs' => 'Scholarship programs', 'offerings' => 'Offerings']"
                :counts="$counts"
            />
        </div>

        <div wire:loading.delay.class="opacity-60" wire:target="kind, restoreProgram, restoreOffering" class="transition-opacity">
            @if ($kind === 'programs')
                @if ($programs->isEmpty())
                    <x-ui.list-empty
                        icon="award"
                        title="No deleted scholarship programs"
                        message="A deleted program stays here for {{ \App\Services\DeletionService::RETENTION_DAYS }} days, ready to restore."
                    />
                @else
                    <x-ui.list-table>
                        <x-slot:head>
                            <th>Scholarship program</th>
                            <th class="md:w-px md:whitespace-nowrap">Offerings</th>
                            <th class="md:w-px md:whitespace-nowrap">Deleted</th>
                            <th class="md:w-px"><span class="sr-only">Actions</span></th>
                        </x-slot:head>

                        @foreach ($programs as $program)
                            <tr wire:key="deleted-program-{{ $program->id }}">
                                <td class="col-start-1 min-w-0 md:max-w-0">
                                    <p class="truncate font-body text-[14px] font-500 text-ink">{{ $program->name }}</p>
                                    <p class="truncate font-body text-[12px] text-mist">{{ $program->provider->name }}</p>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap">
                                    <span class="font-mono text-ink">{{ $program->offerings_count }}</span>
                                    <span class="md:hidden">{{ Str::plural('offering', $program->offerings_count) }}</span>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap">
                                    {{ $program->deleted_at->format('M j, Y') }}
                                    <p class="text-[12px] text-mist">{{ $daysLeft($program->deleted_at) }} {{ Str::plural('day', $daysLeft($program->deleted_at)) }} left</p>
                                </td>
                                <td class="col-start-2 row-span-3 row-start-1 self-center">
                                    <x-ui.button
                                        variant="secondary"
                                        loading="restoreProgram({{ $program->id }})"
                                        wire:click="restoreProgram({{ $program->id }})"
                                    >
                                        <x-ui.icon name="restore" class="size-4" />
                                        Restore
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.list-table>
                @endif
            @else
                @if ($offerings->isEmpty())
                    <x-ui.list-empty
                        icon="calendar"
                        title="No deleted offerings"
                        message="A deleted offering stays here for {{ \App\Services\DeletionService::RETENTION_DAYS }} days, ready to restore."
                    />
                @else
                    <x-ui.list-table>
                        <x-slot:head>
                            <th>Offering</th>
                            <th class="md:w-px md:whitespace-nowrap">Deleted</th>
                            <th class="md:w-px"><span class="sr-only">Actions</span></th>
                        </x-slot:head>

                        @foreach ($offerings as $offering)
                            <tr wire:key="deleted-offering-{{ $offering->id }}">
                                <td class="col-start-1 min-w-0 md:max-w-0">
                                    <p class="truncate font-body text-[14px] font-500 text-ink">{{ $offering->scholarshipProgram->name }} · {{ $offering->displayName() }}</p>
                                    <p class="truncate font-body text-[12px] text-mist">
                                        {{ $offering->academicTerm->school_year }} · {{ $offering->academicTerm->semester->label() }}{{ $offering->isRenewal() ? ' · Renewal' : '' }}
                                    </p>
                                </td>
                                <td class="col-start-1 font-body text-[13px] text-slate md:whitespace-nowrap">
                                    {{ $offering->deleted_at->format('M j, Y') }}
                                    <p class="text-[12px] text-mist">{{ $daysLeft($offering->deleted_at) }} {{ Str::plural('day', $daysLeft($offering->deleted_at)) }} left</p>
                                </td>
                                <td class="col-start-2 row-span-2 row-start-1 self-center">
                                    <x-ui.button
                                        variant="secondary"
                                        loading="restoreOffering({{ $offering->id }})"
                                        wire:click="restoreOffering({{ $offering->id }})"
                                    >
                                        <x-ui.icon name="restore" class="size-4" />
                                        Restore
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.list-table>
                @endif
            @endif
        </div>
    </div>
</div>
