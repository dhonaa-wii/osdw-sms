<?php

namespace App\Services;

use App\Enums\RequirementSubmission;
use App\Models\Application;
use App\Models\ApplicationAdditionalRequirement;
use App\Models\OfferingAdditionalRequirement;
use App\Models\ProgramOffering;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Items staff ask the shortlisted applicants of one offering for on top of its
 * form fields — a hard copy the provider wants, an affidavit the agency asked
 * for late. The list is set once on the offering and applies to everyone on
 * that shortlist; each applicant is then ticked off as their copy arrives.
 *
 * These live on the offering, never on the program, so they leave the
 * requirement template alone and never reach a sibling offering.
 */
class AdditionalRequirementService
{
    /**
     * Upload limits for an applicant-submitted requirement. These are not
     * scholarship policy — they match what the offering form fields are
     * seeded with, so an applicant meets one rule across the whole site.
     */
    public const ACCEPTED_FORMATS = 'pdf,jpg,jpeg,png';

    public const MAX_SIZE_MB = 5;

    /**
     * @param  array<int, array{label: string, submission_mode: string}>  $lines
     * @return Collection<int, OfferingAdditionalRequirement>
     */
    public function add(ProgramOffering $offering, array $lines, User $staff): Collection
    {
        $existing = $offering->additionalRequirements()->pluck('label');

        $lines = collect($lines)
            ->map(fn ($line) => [
                'label' => trim((string) ($line['label'] ?? '')),
                'submission_mode' => RequirementSubmission::tryFrom((string) ($line['submission_mode'] ?? ''))
                    ?? RequirementSubmission::Upload,
            ])
            ->filter(fn (array $line) => $line['label'] !== '')
            ->unique('label')
            ->reject(fn (array $line) => $existing->contains($line['label']))
            ->values();

        return DB::transaction(fn () => $lines->map(fn (array $line) => $offering->additionalRequirements()->create([
            'label' => $line['label'],
            'submission_mode' => $line['submission_mode'],
            'created_by' => $staff->id,
        ])));
    }

    /**
     * Dropping a requirement takes every applicant's receipt for it with it —
     * the item is no longer being asked for, so its tick means nothing.
     */
    public function remove(OfferingAdditionalRequirement $requirement): void
    {
        DB::transaction(function () use ($requirement): void {
            foreach ($requirement->receipts as $receipt) {
                $this->deleteFile($receipt);
            }

            $requirement->delete();
        });
    }

    /**
     * Staff ticking the box at the counter. Only a physical-copy item works
     * this way — an upload is received when the applicant's file lands, and
     * staff confirming one by hand would mean an award with nothing behind it.
     */
    public function markReceived(Application $application, OfferingAdditionalRequirement $requirement, User $staff): ApplicationAdditionalRequirement
    {
        if ($requirement->submission_mode->isUpload()) {
            throw new \RuntimeException('An upload requirement is received when the applicant submits the file.');
        }

        return $application->additionalRequirementReceipts()->firstOrCreate(
            ['offering_additional_requirement_id' => $requirement->id],
            ['received_at' => now(), 'received_by' => $staff->id],
        );
    }

    /**
     * The applicant submitting the item themselves from their tracking page.
     * Their upload counts as received — staff can untick it if it is wrong.
     */
    public function submitUpload(Application $application, OfferingAdditionalRequirement $requirement, UploadedFile $file): ApplicationAdditionalRequirement
    {
        $path = $file->store(
            "applications/{$application->applicant->student_id}/additional/{$requirement->id}",
            'local'
        );

        $receipt = $application->additionalRequirementReceipts()
            ->firstOrNew(['offering_additional_requirement_id' => $requirement->id]);

        $this->deleteFile($receipt);

        $receipt->fill([
            'received_at' => now(),
            'received_by' => null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ])->save();

        return $receipt;
    }

    public function markOutstanding(Application $application, OfferingAdditionalRequirement $requirement): void
    {
        $receipt = $application->additionalRequirementReceipts()
            ->where('offering_additional_requirement_id', $requirement->id)
            ->first();

        if (! $receipt) {
            return;
        }

        $this->deleteFile($receipt);
        $receipt->delete();
    }

    protected function deleteFile(ApplicationAdditionalRequirement $receipt): void
    {
        if ($receipt->file_path) {
            Storage::disk('local')->delete($receipt->file_path);
        }
    }
}
