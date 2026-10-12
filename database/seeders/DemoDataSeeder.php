<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\RequirementStage;
use App\Enums\SubmissionMode;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusLog;
use App\Models\College;
use App\Models\OfferingAdditionalRequirement;
use App\Models\OfferingFormField;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\User;
use App\Services\AwardService;
use App\Services\ScreeningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Realistic demo content layered on top of DatabaseSeeder so every
 * redesigned screen has something to show instead of empty states.
 * Everything here is throwaway — reset it any time with:
 *   php artisan migrate:fresh --seed
 * (this seeder is intentionally NOT called from DatabaseSeeder, so a
 * plain fresh+seed cycle drops it automatically).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // DatabaseSeeder does not seed degree programs — the real list is agency-defined
        // and has not been supplied yet. An application needs a college to sit in, so the
        // demo data carries a placeholder program per college and throws it away with the
        // rest of the seed. Replace these once the registrar's list arrives.
        $programs = $this->demoPrograms();

        $staff = User::where('role', 'osdw_staff')->firstOrFail();

        $offeringA = ProgramOffering::whereHas('scholarshipProgram', fn ($q) => $q->where('name', 'DOST-SEI Undergraduate Academic Fellowship'))->firstOrFail();
        $offeringB = ProgramOffering::whereHas('scholarshipProgram', fn ($q) => $q->where('name', 'CSUA Alumni Association Scholarship'))->firstOrFail();

        $corField = $offeringA->formFields()->where('label', 'Certificate of Registration')->first();

        // --- Offering A's additional requirements and interview -------------

        $contract = $this->requirement($offeringA, RequirementStage::Shortlist, 'Signed scholarship contract', SubmissionMode::Both, 'Download the blank form, have it signed by you and a parent, upload a scan, then hand in the original at the OSDW office, Room 204.');
        $contract->update([
            'template_path' => $this->placeholderFile('demo/templates/scholarship-contract.pdf', 'Scholarship Contract - blank form'),
            'template_name' => 'scholarship-contract.pdf',
        ]);
        $itr = $this->requirement($offeringA, RequirementStage::Shortlist, "Parent's income tax return", SubmissionMode::Upload, 'Latest ITR or a BIR certificate of tax exemption.');
        $bankDetails = $this->requirement($offeringA, RequirementStage::Award, 'Landbank ATM account details', SubmissionMode::Upload, 'A photo of the front of your ATM card or your passbook.');
        $this->requirement($offeringA, RequirementStage::Award, 'Original certificate of grades', SubmissionMode::Physical, 'Hand in at the OSDW office, Room 204.');

        $offeringA->update([
            'has_interview' => true,
            'interview_at' => now()->addDays(5)->setTime(9, 0),
            'interview_venue' => 'OSDW Conference Room, Administration Building',
            'interview_instructions' => 'Wear your school uniform and bring your school ID and a printed copy of your application.',
        ]);

        // --- Applicants across the pipeline -------------------------------

        $juan = $this->applicant('2022-00341', 'Dela Cruz', 'Juan', 'juan.delacruz@example.test', 'male');
        $juanApp = $this->submit($offeringA, $juan, $programs['BSIT'], $corField, ['gwa' => 92.00, 'year_level' => 3], daysAgo: 18);
        $this->transition($juanApp, 'shortlisted', at: now()->subDays(14));
        $this->requirementSubmission($juanApp, $contract, $staff, DocumentStatus::Verified, physical: true);
        $this->requirementSubmission($juanApp, $itr, $staff, DocumentStatus::Verified);
        app(AwardService::class)->create($juanApp, $staff);
        $this->requirementSubmission($juanApp, $bankDetails, $staff, DocumentStatus::Verified);
        // Second application, same student — drives the multi-application badge.
        $this->submit($offeringB, $juan, $programs['BSIT'], null, ['gwa' => 92.00, 'year_level' => 3], daysAgo: 4);

        $maria = $this->applicant('2022-00587', 'Santos', 'Maria', 'maria.santos@example.test', 'female');
        $mariaApp = $this->submit($offeringA, $maria, $programs['BSCS'], $corField, ['gwa' => 90.00, 'year_level' => 2, 'consent_publish' => false], daysAgo: 15);
        $this->transition($mariaApp, 'shortlisted', at: now()->subDays(12));
        $this->requirementSubmission($mariaApp, $contract, $staff, DocumentStatus::Verified, physical: true);
        $this->requirementSubmission($mariaApp, $itr, $staff, DocumentStatus::Verified);
        app(AwardService::class)->create($mariaApp, $staff);
        $this->requirementSubmission($mariaApp, $bankDetails, $staff, DocumentStatus::Pending);

        // Shortlisted, one upload waiting on staff and one not yet submitted.
        $patricia = $this->applicant('2023-00129', 'Gomez', 'Patricia', 'patricia.gomez@example.test', 'female');
        $patriciaApp = $this->submit($offeringA, $patricia, $programs['BSA'], $corField, ['gwa' => 88.00, 'year_level' => 1], daysAgo: 10);
        $this->transition($patriciaApp, 'shortlisted', at: now()->subDays(6));
        $this->requirementSubmission($patriciaApp, $contract, $staff, DocumentStatus::Pending);

        $mark = $this->applicant('2021-00204', 'Villanueva', 'Mark', 'mark.villanueva@example.test', 'male');
        $this->submit($offeringA, $mark, $programs['BIT'], $corField, ['gwa' => 87.00, 'year_level' => 4], daysAgo: 9);

        $angel = $this->applicant('2022-00778', 'Reyes', 'Angel', 'angel.reyes@example.test', 'female');
        $this->submit($offeringA, $angel, $programs['BSHM'], $corField, ['gwa' => 84.00, 'year_level' => 2], daysAgo: 3);

        $kim = $this->applicant('2023-00042', 'Bautista', 'Kim', 'kim.bautista@example.test', 'female');
        $this->submit($offeringA, $kim, $programs['BEED'], $corField, ['gwa' => 72.00, 'year_level' => 1], daysAgo: 7);

        $rico = $this->applicant('2022-00915', 'Fernandez', 'Rico', 'rico.fernandez@example.test', 'male');
        $this->submit($offeringA, $rico, $programs['BSED'], null, ['gwa' => 86.00, 'year_level' => 3], daysAgo: 6);

        $divine = $this->applicant('2021-00456', 'Cruz', 'Divine', 'divine.cruz@example.test', 'female');
        $divineApp = $this->submit($offeringA, $divine, $programs['BSBA'], $corField, ['gwa' => 83.00, 'year_level' => 3], daysAgo: 11);
        $this->transition($divineApp, 'denied', 'Did not meet the GWA requirement.', now()->subDays(2));

        // --- More of the shortlist, still awaiting award -------------------

        $noel = $this->applicant('2021-00873', 'Aquino', 'Noel', 'noel.aquino@example.test', 'male');
        $noelApp = $this->submit($offeringA, $noel, $programs['BSCRIM'], $corField, ['gwa' => 89.00, 'year_level' => 4], daysAgo: 13);
        $this->transition($noelApp, 'shortlisted', at: now()->subDays(8));
        // Everything complied — ready to award.
        $this->requirementSubmission($noelApp, $contract, $staff, DocumentStatus::Verified, physical: true);
        $this->requirementSubmission($noelApp, $itr, $staff, DocumentStatus::Verified);

        // Upload verified but original not handed in; ITR sent back.
        $liza = $this->applicant('2022-00661', 'Mendoza', 'Liza', 'liza.mendoza@example.test', 'female');
        $lizaApp = $this->submit($offeringA, $liza, $programs['BSED'], $corField, ['gwa' => 91.00, 'year_level' => 2], daysAgo: 12);
        $this->transition($lizaApp, 'shortlisted', at: now()->subDays(7));
        $this->requirementSubmission($lizaApp, $contract, $staff, DocumentStatus::Verified);
        $this->requirementSubmission($lizaApp, $itr, $staff, DocumentStatus::Rejected, remarks: 'The scan is cut off at the bottom. Upload the full page, including the BIR stamp.');

        $joel = $this->applicant('2023-00318', 'Ramos', 'Joel', 'joel.ramos@example.test', 'male');
        $joelApp = $this->submit($offeringA, $joel, $programs['BSCS'], $corField, ['gwa' => 85.00, 'year_level' => 1], daysAgo: 9);
        $this->transition($joelApp, 'shortlisted', at: now()->subDays(5));

        // --- More applicants still in the screening queue -------------------

        $grace = $this->applicant('2022-00234', 'Pascual', 'Grace', 'grace.pascual@example.test', 'female');
        $this->submit($offeringA, $grace, $programs['BSBA'], $corField, ['gwa' => 88.50, 'year_level' => 2], daysAgo: 5);

        $arnel = $this->applicant('2021-00719', 'Domingo', 'Arnel', 'arnel.domingo@example.test', 'male');
        $this->submit($offeringA, $arnel, $programs['BSF'], $corField, ['gwa' => 81.00, 'year_level' => 4], daysAgo: 8);

        $sheila = $this->applicant('2023-00505', 'Tolentino', 'Sheila', 'sheila.tolentino@example.test', 'female');
        $this->submit($offeringB, $sheila, $programs['BEED'], null, ['gwa' => 90.50, 'year_level' => 1], daysAgo: 2);

        // --- Publish results + announcements -------------------------------

        $offeringA->update([
            'results_published' => true,
            'publish_instructions' => 'Awardees may claim their certificate at the OSDW office, Room 204, starting next week. Bring one valid school ID.',
            'results_published_at' => now()->subDays(1),
        ]);

        Announcement::create([
            'title' => 'Application window extended for DOST-SEI Fellowship',
            'body' => 'Due to high demand, OSDW has extended the submission deadline. Late applicants are still encouraged to apply.',
            'is_published' => true,
            'published_at' => now()->subDays(5),
            'created_by' => $staff->id,
        ]);
        Announcement::create([
            'title' => 'Document verification schedule posted',
            'body' => 'Applicants with pending document verification will be contacted via email within 5 working days of submission.',
            'is_published' => true,
            'published_at' => now()->subDays(2),
            'created_by' => $staff->id,
        ]);
        Announcement::create([
            'program_offering_id' => $offeringA->id,
            'title' => 'First shortlist published',
            'body' => 'Applicants who passed screening for the DOST-SEI Fellowship have been notified by email. Check your application status for details.',
            'is_published' => true,
            'published_at' => now()->subDays(1),
            'created_by' => $staff->id,
        ]);
    }

    /**
     * @return Collection<string, Program>
     */
    protected function demoPrograms(): Collection
    {
        $colleges = College::all()->keyBy('code');

        return collect([
            ['college' => 'CICS', 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
            ['college' => 'CICS', 'code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science'],
            ['college' => 'CTE', 'code' => 'BEED', 'name' => 'Bachelor of Elementary Education'],
            ['college' => 'CTE', 'code' => 'BSED', 'name' => 'Bachelor of Secondary Education'],
            ['college' => 'CBEA', 'code' => 'BSBA', 'name' => 'Bachelor of Science in Business Administration'],
            ['college' => 'CBEA', 'code' => 'BSA', 'name' => 'Bachelor of Science in Accountancy'],
            ['college' => 'CCJE', 'code' => 'BSCRIM', 'name' => 'Bachelor of Science in Criminology'],
            ['college' => 'CFAS', 'code' => 'BSF', 'name' => 'Bachelor of Science in Fisheries'],
            ['college' => 'CHM', 'code' => 'BSHM', 'name' => 'Bachelor of Science in Hospitality Management'],
            ['college' => 'CIT', 'code' => 'BIT', 'name' => 'Bachelor of Industrial Technology'],
        ])
            ->filter(fn ($attributes) => $colleges->has($attributes['college']))
            ->mapWithKeys(fn ($attributes) => [
                $attributes['code'] => Program::updateOrCreate(
                    ['code' => $attributes['code']],
                    [
                        'college_id' => $colleges[$attributes['college']]->id,
                        'name' => $attributes['name'],
                        'degree_level' => 'undergraduate',
                    ]
                ),
            ]);
    }

    protected function applicant(string $studentId, string $lastName, string $firstName, string $email, string $sex): Applicant
    {
        // Every demo student can sign in with their email and "password".
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => "{$firstName} {$lastName}",
                'password' => 'password',
                'role' => UserRole::Student,
                'email_verified_at' => now(),
            ]
        );

        return Applicant::updateOrCreate(
            ['student_id' => $studentId],
            [
                'user_id' => $user->id,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'sex' => $sex,
                'birthdate' => now()->subYears(20)->subDays(random_int(0, 700)),
                'email' => $email,
                'mobile' => '09'.random_int(100000000, 999999999),
                'address' => 'Aparri, Cagayan',
            ]
        );
    }

    protected function submit(
        ProgramOffering $offering,
        Applicant $applicant,
        Program $program,
        ?OfferingFormField $corField,
        array $overrides,
        int $daysAgo,
    ): Application {
        $submittedAt = now()->subDays($daysAgo);

        $application = Application::create(array_merge([
            'applicant_id' => $applicant->id,
            'program_offering_id' => $offering->id,
            'college_id' => $program->college_id,
            'consent_publish' => true,
            'status' => 'submitted',
            'submitted_at' => $submittedAt,
        ], $overrides));

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'created_at' => $submittedAt,
        ]);

        if ($corField) {
            $path = $this->placeholderFile(
                'demo/cor-'.$application->id.'.pdf',
                'Certificate of Registration - '.$applicant->student_id
            );

            ApplicationDocument::create([
                'application_id' => $application->id,
                'offering_form_field_id' => $corField->id,
                'file_path' => $path,
                'original_name' => 'certificate-of-registration.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => Storage::disk('local')->size($path),
                'status' => 'pending',
            ]);
        }

        app(ScreeningService::class)->screen($application);

        return $application->fresh();
    }

    protected function requirement(
        ProgramOffering $offering,
        RequirementStage $stage,
        string $label,
        SubmissionMode $mode,
        string $instructions,
    ): OfferingAdditionalRequirement {
        return $offering->additionalRequirements()->firstOrCreate(
            ['stage' => $stage->value, 'label' => $label],
            [
                'instructions' => $instructions,
                'submission_mode' => $mode,
                'accepted_formats' => $mode->needsUpload() ? 'pdf,jpg,png' : null,
                'max_size_mb' => $mode->needsUpload() ? 5 : null,
                'sort_order' => $offering->additionalRequirements()->where('stage', $stage->value)->count() + 1,
            ]
        );
    }

    /**
     * An applicant's submission against one additional requirement. $status
     * describes the upload (ignored for physical-only requirements); $physical
     * marks the original as received at the office.
     */
    protected function requirementSubmission(
        Application $application,
        OfferingAdditionalRequirement $requirement,
        User $staff,
        DocumentStatus $status,
        bool $physical = false,
        ?string $remarks = null,
    ): void {
        $attributes = [];

        if ($requirement->submission_mode->needsUpload()) {
            $path = $this->placeholderFile(
                'demo/requirement-'.$application->id.'-'.$requirement->id.'.pdf',
                $requirement->label.' - '.$application->applicant->student_id
            );

            $attributes = [
                'file_path' => $path,
                'original_name' => Str::slug($requirement->label).'.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => Storage::disk('local')->size($path),
                'submitted_at' => now()->subDays(2),
                'status' => $status,
                'remarks' => $remarks,
                'verified_by' => $status === DocumentStatus::Pending ? null : $staff->id,
                'verified_at' => $status === DocumentStatus::Pending ? null : now()->subDay(),
            ];
        }

        if ($physical && $requirement->submission_mode->needsPhysical()) {
            $attributes += [
                'physical_received_at' => now()->subDay(),
                'physical_received_by' => $staff->id,
            ];
        }

        $application->additionalRequirementSubmissions()->updateOrCreate(
            ['offering_additional_requirement_id' => $requirement->id],
            $attributes
        );
    }

    protected function transition(Application $application, string $toStatus, ?string $reason = null, $at = null): void
    {
        $from = $application->status;
        $application->update(['status' => $toStatus]);

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'reason' => $reason,
            'created_at' => $at ?? now(),
        ]);
    }

    /**
     * Writes a real one-page PDF to the local disk so the demo's View links
     * open something instead of failing on a missing file. Returns the path.
     */
    protected function placeholderFile(string $path, string $title): string
    {
        Storage::disk('local')->put($path, $this->onePagePdf($title));

        return $path;
    }

    /**
     * A minimal but valid PDF — five objects and a hand-built xref table whose
     * offsets are measured as the document is assembled.
     */
    protected function onePagePdf(string $title): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $title);
        $stream = "BT /F1 16 Tf 72 720 Td ({$text}) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $startXref = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$startXref}\n%%EOF";
    }
}
