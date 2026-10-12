<?php

namespace App\Livewire\Concerns;

use App\Enums\UserRole;
use App\Models\ProgramOffering;
use App\Services\DeletionService;

/**
 * Backs the Delete action in the offering chain tabs' menu
 * (<x-staff.offering-chain-tabs>). Admin only. Deleting the offering being
 * viewed moves to the offering it renewed — or, for a group's first
 * offering, back to the program's offerings; deleting another tab reloads
 * the current page.
 */
trait DeletesOfferings
{
    public function deleteOffering(int $offeringId, DeletionService $deletionService): void
    {
        abort_unless(auth()->user()->role === UserRole::Admin, 403);

        $target = ProgramOffering::findOrFail($offeringId);

        try {
            $previous = $deletionService->deleteOffering($target);
        } catch (\RuntimeException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Offering not deleted', message: $exception->getMessage());

            return;
        }

        session()->flash('notice', ['title' => 'Offering deleted', 'message' => 'Restore it from Settings → Deleted items within '.DeletionService::RETENTION_DAYS.' days.']);

        $landOn = $target->is($this->offering) ? $previous : $this->offering;

        if ($landOn === null) {
            $this->redirect(route('admin.scholarship-programs.offerings', $target->scholarship_program_id), navigate: true);

            return;
        }

        $this->redirect(match (true) {
            $this->chainLinksToAdmin() => route('admin.offerings.view', $landOn),
            $landOn->isRenewal() => route('staff.renewals.index', $landOn),
            default => route('staff.applications.workspace', $landOn),
        }, navigate: true);
    }

    /**
     * Whether this page's chain tabs open admin offering pages.
     */
    protected function chainLinksToAdmin(): bool
    {
        return false;
    }
}
