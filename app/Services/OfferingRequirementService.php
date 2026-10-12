<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\RequirementStage;
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
 * Everything an offering asks of applicants after screening, configured on
 * its Requirements page:
 *
 * - Additional requirements, each tied to a stage. Shortlist requirements
 *   gate the award — AwardService refuses to award while any is outstanding.
 *   Award requirements are asked of awardees afterwards, for disbursement.
 *   Renewal requirements belong to a renewal offering and gate the renewal.
 * - The interview schedule — one date, venue and set of instructions shown
 *   to every shortlisted applicant on their tracking page.
 */
class OfferingRequirementService
{
    /**
     * @param  array{stage: string, label: string, instructions: ?string, submission_mode: string, accepted_formats: ?string, max_size_mb: ?int}  $attributes
     */
    public function saveRequirement(
        ProgramOffering $offering,
        array $attributes,
        ?int $requirementId = null,
        ?UploadedFile $template = null,
        bool $removeTemplate = false,
    ): OfferingAdditionalRequirement {
        if ($requirementId !== null) {
            $requirement = $offering->additionalRequirements()->findOrFail($requirementId);
            $requirement->update($attributes);
        } else {
            $nextOrder = (int) $offering->additionalRequirements()
                ->where('stage', $attributes['stage'])
                ->max('sort_order') + 1;

            $requirement = $offering->additionalRequirements()->create($attributes + ['sort_order' => $nextOrder]);
        }

        if ($template !== null) {
            $this->storeTemplate($requirement, $template);
        } elseif ($removeTemplate) {
            $this->deleteTemplate($requirement);
        }

        return $requirement;
    }

    /**
     * A blank form applicants download, fill in and hand back — e.g. the
     * scholarship contract. Replaces any earlier one.
     */
    protected function storeTemplate(OfferingAdditionalRequirement $requirement, UploadedFile $file): void
    {
        $this->deleteTemplate($requirement);

        $requirement->update([
            'template_path' => $file->store("offerings/{$requirement->program_offering_id}/templates", 'local'),
            'template_name' => $file->getClientOriginalName(),
        ]);
    }

    protected function deleteTemplate(OfferingAdditionalRequirement $requirement): void
    {
        if ($requirement->template_path) {
            Storage::disk('local')->delete($requirement->template_path);
        }

        $requirement->update(['template_path' => null, 'template_name' => null]);
    }

    /**
     * Removing a requirement takes every applicant's submission for it along
     * — including the uploaded files and its blank form, so nothing is left
     * orphaned on disk.
     */
    public function removeRequirement(OfferingAdditionalRequirement $requirement): void
    {
        DB::transaction(function () use ($requirement): void {
            foreach ($requirement->submissions as $submission) {
                $this->deleteFile($submission);
            }

            if ($requirement->template_path) {
                Storage::disk('local')->delete($requirement->template_path);
            }

            $requirement->delete();
        });
    }

    /**
     * Swap a requirement with its neighbour within the same stage.
     */
    public function move(OfferingAdditionalRequirement $requirement, int $direction): void
    {
        $siblings = OfferingAdditionalRequirement::where('program_offering_id', $requirement->program_offering_id)
            ->where('stage', $requirement->stage)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn ($sibling) => $sibling->id === $requirement->id);
        $target = $index + $direction;

        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $sibling) {
                $sibling->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * @param  array{has_interview: bool, interview_at: ?string, interview_venue: ?string, interview_instructions: ?string}  $attributes
     */
    public function saveInterview(ProgramOffering $offering, array $attributes): void
    {
        $wasScheduled = $offering->hasScheduledInterview();

        $offering->update($attributes['has_interview'] ? $attributes : [
            'has_interview' => false,
            'interview_at' => null,
            'interview_venue' => null,
            'interview_instructions' => null,
        ]);

        // Shortlisted applicants hear about a new date or venue, not an edit to the instructions.
        if ($offering->hasScheduledInterview() && $offering->wasChanged(['has_interview', 'interview_at', 'interview_venue'])) {
            app(StudentNotificationService::class)->interviewScheduled($offering, rescheduled: $wasScheduled);
            app(StaffNotificationService::class)->interviewScheduled($offering, rescheduled: $wasScheduled);
        }
    }

    /**
     * The stages an applicant is currently being asked to complete. A
     * shortlisted applicant works on the shortlist stage; an awardee on the
     * disbursement stage; a renewal under review on the renewal stage.
     * Shortlist and disbursement requirements count only once staff have
     * opened them. Everyone else has nothing outstanding.
     *
     * @return array<int, RequirementStage>
     */
    public function activeStages(Application $application): array
    {
        if ($application->isRenewal()) {
            return $application->status === ApplicationStatus::Screening ? [RequirementStage::Renewal] : [];
        }

        $stage = match ($application->status) {
            ApplicationStatus::Shortlisted => RequirementStage::Shortlist,
            ApplicationStatus::Awarded => RequirementStage::Award,
            default => null,
        };

        return $stage !== null && $application->programOffering->hasOpenedRequirements($stage) ? [$stage] : [];
    }

    /**
     * One row per requirement of the given stage, paired with the
     * applicant's submission (if any) and whether it is complied.
     *
     * @return Collection<int, array{requirement: OfferingAdditionalRequirement, submission: ?ApplicationAdditionalRequirement, complied: bool}>
     */
    public function checklist(Application $application, RequirementStage $stage): Collection
    {
        $submissions = $application->additionalRequirementSubmissions()
            ->with('requirement')
            ->get()
            ->keyBy('offering_additional_requirement_id');

        return $application->programOffering->additionalRequirements()
            ->where('stage', $stage->value)
            ->get()
            ->map(function (OfferingAdditionalRequirement $requirement) use ($submissions) {
                $submission = $submissions->get($requirement->id);

                return [
                    'requirement' => $requirement,
                    'submission' => $submission,
                    'complied' => $submission?->isComplied() ?? false,
                ];
            });
    }

    public function outstandingCount(Application $application, RequirementStage $stage): int
    {
        return $this->checklist($application, $stage)->reject(fn ($row) => $row['complied'])->count();
    }

    /**
     * The applicant uploading a file from their tracking page. Replaces any
     * earlier file and sends it back to staff for review; a verified upload
     * cannot be replaced.
     */
    public function submitUpload(Application $application, OfferingAdditionalRequirement $requirement, UploadedFile $file): ApplicationAdditionalRequirement
    {
        if (! $requirement->submission_mode->needsUpload()) {
            throw new \RuntimeException('This requirement is submitted as a physical copy.');
        }

        $offering = $application->programOffering;

        if ($requirement->stage === RequirementStage::Renewal && ! $offering->acceptsRenewalSubmissions()) {
            throw new \RuntimeException(now()->lt($offering->opens_at)
                ? 'Renewal submissions open on '.$offering->opens_at->format('M j, Y · g:i A').'.'
                : 'The renewal submission window closed on '.$offering->closes_at->format('M j, Y · g:i A').'. Contact the OSDW office.');
        }

        $submission = $this->submissionFor($application, $requirement);

        if ($submission->exists && $submission->file_path && $submission->status === DocumentStatus::Verified) {
            throw new \RuntimeException('This upload is already verified.');
        }

        // Disbursement and renewal requirements are submitted once; they can be uploaded again only after OSDW returns them.
        if ($requirement->stage->isSubmittedOnce() && $submission->exists && $submission->file_path && $submission->status !== DocumentStatus::Rejected) {
            throw new \RuntimeException('You already submitted this requirement.');
        }

        $this->deleteFile($submission);

        $submission->fill([
            'file_path' => $file->store("applications/{$application->applicant->student_id}/additional/{$requirement->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'submitted_at' => now(),
            'status' => DocumentStatus::Pending,
            'remarks' => null,
            'verified_by' => null,
            'verified_at' => null,
        ])->save();

        return $submission;
    }

    public function verify(ApplicationAdditionalRequirement $submission, User $staff): void
    {
        $submission->update([
            'status' => DocumentStatus::Verified,
            'remarks' => null,
            'verified_by' => $staff->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * Send an upload back to the applicant with a reason; they see the
     * remarks on their tracking page and can upload again.
     */
    public function reject(ApplicationAdditionalRequirement $submission, string $remarks, User $staff): void
    {
        $submission->update([
            'status' => DocumentStatus::Rejected,
            'remarks' => $remarks,
            'verified_by' => $staff->id,
            'verified_at' => now(),
        ]);
    }

    public function togglePhysicalReceived(Application $application, OfferingAdditionalRequirement $requirement, User $staff): void
    {
        if (! $requirement->submission_mode->needsPhysical()) {
            throw new \RuntimeException('This requirement is submitted as an upload only.');
        }

        $submission = $this->submissionFor($application, $requirement);
        $received = $submission->physical_received_at === null;

        $submission->fill([
            'physical_received_at' => $received ? now() : null,
            'physical_received_by' => $received ? $staff->id : null,
        ])->save();
    }

    protected function submissionFor(Application $application, OfferingAdditionalRequirement $requirement): ApplicationAdditionalRequirement
    {
        return $application->additionalRequirementSubmissions()
            ->firstOrNew(['offering_additional_requirement_id' => $requirement->id]);
    }

    protected function deleteFile(ApplicationAdditionalRequirement $submission): void
    {
        if ($submission->file_path) {
            Storage::disk('local')->delete($submission->file_path);
        }
    }
}
