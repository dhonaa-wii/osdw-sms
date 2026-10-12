<?php

namespace App\Services;

use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting and restoring scholarship programs and offerings. A delete is a
 * soft delete: the row stays restorable from Settings → Deleted items for
 * RETENTION_DAYS, then purgeExpired() removes it for good.
 *
 * Only configuration can be deleted — never an offering that holds
 * applications or renewals, since those are records of real students.
 */
class DeletionService
{
    public const RETENTION_DAYS = 60;

    /**
     * Delete one offering. Nothing may depend on it: no applications or
     * renewals in it, and no later renewal offering continuing it. Returns the
     * offering it renewed, if any, so the caller can land there.
     */
    public function deleteOffering(ProgramOffering $offering): ?ProgramOffering
    {
        if ($offering->renewalOfferings()->exists()) {
            throw new \RuntimeException('A renewal offering continues this one. Delete that one first.');
        }

        if ($offering->applications()->exists()) {
            throw new \RuntimeException('This offering already holds applications or renewals, so it can\'t be deleted.');
        }

        $offering->delete();

        return $offering->renewsOffering;
    }

    /**
     * Delete a scholarship program with every offering it has. Its offerings
     * share the program's deletion time, which is how restoreProgram() knows
     * which ones to bring back.
     */
    public function deleteProgram(ScholarshipProgram $program): void
    {
        if ($program->offerings()->whereHas('applications')->exists()) {
            throw new \RuntimeException('One of its offerings already holds applications or renewals, so this program can\'t be deleted.');
        }

        DB::transaction(function () use ($program): void {
            $program->delete();

            $program->offerings()->update(['deleted_at' => $program->deleted_at]);
        });
    }

    public function restoreOffering(ProgramOffering $offering): void
    {
        if ($offering->scholarshipProgram()->withTrashed()->first()?->trashed()) {
            throw new \RuntimeException('Its scholarship program is deleted. Restore the program instead.');
        }

        $renews = $offering->renewsOffering()->withTrashed()->first();

        if ($renews?->trashed()) {
            throw new \RuntimeException("It renews {$renews->displayName()}, which is deleted. Restore that offering first.");
        }

        $offering->restore();
    }

    /**
     * Bring back a program and the offerings deleted with it. Offerings
     * deleted on their own before that stay deleted.
     */
    public function restoreProgram(ScholarshipProgram $program): void
    {
        DB::transaction(function () use ($program): void {
            ProgramOffering::onlyTrashed()
                ->where('scholarship_program_id', $program->id)
                ->where('deleted_at', $program->deleted_at)
                ->restore();

            $program->restore();
        });
    }

    /**
     * When a deleted program or offering is removed for good.
     */
    public function purgesOn(CarbonInterface $deletedAt): CarbonInterface
    {
        return $deletedAt->copy()->addDays(self::RETENTION_DAYS);
    }

    /**
     * Permanently remove everything deleted more than RETENTION_DAYS ago.
     * Returns how many programs and offerings were removed.
     */
    public function purgeExpired(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $purged = 0;

        $offerings = ProgramOffering::onlyTrashed()->where('deleted_at', '<=', $cutoff)->get();

        // A renewal offering points at the one it renews, so the later
        // offering has to go first. One whose renewal offering is not due yet
        // waits for a later run.
        do {
            $progress = false;

            foreach ($offerings as $key => $offering) {
                if (ProgramOffering::withTrashed()->where('renews_offering_id', $offering->id)->exists()) {
                    continue;
                }

                $this->purgeOffering($offering);
                $offerings->forget($key);
                $purged++;
                $progress = true;
            }
        } while ($progress && $offerings->isNotEmpty());

        ScholarshipProgram::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereDoesntHave('offerings', fn ($query) => $query->withTrashed())
            ->get()
            ->each(function (ScholarshipProgram $program) use (&$purged): void {
                $program->forceDelete();
                $purged++;
            });

        return $purged;
    }

    protected function purgeOffering(ProgramOffering $offering): void
    {
        DB::transaction(function () use ($offering): void {
            // The foreign key would otherwise turn them into general announcements.
            $offering->announcements()->delete();

            // Form fields, eligibility rules, colleges and requirements cascade.
            $offering->forceDelete();
        });

        // Blank forms uploaded for its requirements.
        Storage::disk('local')->deleteDirectory("offerings/{$offering->id}");
    }
}
