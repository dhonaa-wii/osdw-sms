<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationFieldResponse;
use App\Models\ApplicationStatusLog;
use App\Models\ProgramOffering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    public function __construct(
        protected ScreeningService $screeningService,
        protected StudentNotificationService $notifications,
        protected EligibilityService $eligibility,
        protected ScholarshipHoldingService $holdings,
    ) {}

    /**
     * The signed-in student's applicant record supplies their identity; the
     * form supplies this term's academic standing and the offering's fields.
     *
     * @param  array<string, mixed>  $coreData
     * @param  array<int, mixed>  $fieldResponses  keyed by offering_form_field_id
     * @param  array<int, UploadedFile>  $uploadedFiles  keyed by offering_form_field_id
     */
    public function submit(
        ProgramOffering $offering,
        Applicant $applicant,
        array $coreData,
        array $fieldResponses,
        array $uploadedFiles,
    ): Application {
        if ($applicant->applications()->where('program_offering_id', $offering->id)->exists()) {
            throw new \RuntimeException("You've already applied to this offering.");
        }

        if ($blocker = $this->holdings->applyBlocker($applicant, $offering)) {
            throw new RuntimeException($blocker);
        }

        if ($unmetRule = $this->eligibility->unmetGwaRule($offering, $coreData['gwa'])) {
            throw new \RuntimeException($unmetRule->message);
        }

        if ($unmetRule = $this->eligibility->unmetIncomeRule($offering, $coreData['monthly_income'])) {
            throw new \RuntimeException($unmetRule->message);
        }

        $application = DB::transaction(function () use ($offering, $applicant, $coreData, $fieldResponses, $uploadedFiles) {
            $application = Application::create([
                'applicant_id' => $applicant->id,
                'program_offering_id' => $offering->id,
                'college_id' => $coreData['college_id'],
                'year_level' => $coreData['year_level'],
                'gwa' => $coreData['gwa'] !== '' ? $coreData['gwa'] : null,
                'monthly_income' => $coreData['monthly_income'] !== '' ? $coreData['monthly_income'] : null,
                'consent_publish' => $coreData['consent_publish'],
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => null,
                'to_status' => 'submitted',
            ]);

            foreach ($offering->formFields as $field) {
                if ($field->field_type->isFile()) {
                    $file = $uploadedFiles[$field->id] ?? null;

                    if ($file instanceof UploadedFile) {
                        $path = $file->store("applications/{$applicant->student_id}/{$field->id}", 'local');

                        ApplicationDocument::create([
                            'application_id' => $application->id,
                            'offering_form_field_id' => $field->id,
                            'file_path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                            'status' => 'pending',
                        ]);
                    }

                    continue;
                }

                $value = $fieldResponses[$field->id] ?? null;

                if ($value !== null && $value !== '') {
                    ApplicationFieldResponse::create([
                        'application_id' => $application->id,
                        'offering_form_field_id' => $field->id,
                        'value' => (string) $value,
                    ]);
                }
            }

            return $application;
        });

        $this->screeningService->screen($application);

        // This submission may have filled the application cap and closed registration.
        $this->notifications->syncRegistrationNotices();

        return $application->fresh();
    }
}
