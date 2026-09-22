<?php

namespace App\Services;

use App\Enums\EmailType;
use App\Mail\ApplicationReferenceMail;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationFieldResponse;
use App\Models\ApplicationStatusLog;
use App\Models\ProgramOffering;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationService
{
    public function __construct(protected EmailService $emailService, protected ScreeningService $screeningService) {}

    /**
     * @param  array<string, mixed>  $applicantData
     * @param  array<string, mixed>  $coreData
     * @param  array<int, mixed>  $fieldResponses  keyed by offering_form_field_id
     * @param  array<int, UploadedFile>  $uploadedFiles  keyed by offering_form_field_id
     */
    public function submit(
        ProgramOffering $offering,
        array $applicantData,
        array $coreData,
        array $fieldResponses,
        array $uploadedFiles,
    ): Application {
        $application = DB::transaction(function () use ($offering, $applicantData, $coreData, $fieldResponses, $uploadedFiles) {
            $applicant = Applicant::updateOrCreate(
                ['student_id' => $applicantData['student_id']],
                $applicantData
            );

            $application = Application::create([
                'reference_no' => $this->generateReferenceNumber(),
                'access_token' => Str::random(64),
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

        $this->emailService->send(
            $application,
            EmailType::Reference,
            new ApplicationReferenceMail($application),
            $application->applicant->email,
        );

        $this->screeningService->screen($application);

        return $application->fresh();
    }

    protected function generateReferenceNumber(): string
    {
        do {
            $candidate = 'SCH-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (Application::where('reference_no', $candidate)->exists());

        return $candidate;
    }
}
