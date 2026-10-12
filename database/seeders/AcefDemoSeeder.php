<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\User;
use App\Services\AwardService;
use App\Services\ScreeningService;

/**
 * Demo applicants for the admin-created "ACEF-GIAHEP Grant-in-Aid 1"
 * offering: some in the screening queue, some shortlisted, some awarded.
 * Run on top of DemoDataSeeder with:
 *   php artisan db:seed --class=AcefDemoSeeder
 * It does nothing if the offering is missing or already has applications.
 */
class AcefDemoSeeder extends DemoDataSeeder
{
    public function run(): void
    {
        $offering = ProgramOffering::where('name', 'ACEF-GIAHEP Grant-in-Aid 1')
            ->whereHas('scholarshipProgram', fn ($q) => $q->where('name', 'ACEF-GIAHEP Grant-in-Aid'))
            ->first();

        if (! $offering || $offering->applications()->exists()) {
            return;
        }

        $programs = $this->demoPrograms();
        $staff = User::where('role', 'osdw_staff')->firstOrFail();
        $coeField = $offering->formFields()->where('label', 'COE')->first();

        // --- Awarded ------------------------------------------------------

        foreach ([
            ['2024-00112', 'Castillo', 'Andrea', 'female', 'BSCS', 99.50, 1, 16],
            ['2024-00157', 'Navarro', 'Paolo', 'male', 'BSIT', 99.25, 1, 15],
            ['2024-00203', 'Salazar', 'Bea', 'female', 'BSA', 99.00, 1, 14],
        ] as [$id, $last, $first, $sex, $program, $gwa, $year, $daysAgo]) {
            $application = $this->apply($offering, $id, $last, $first, $sex, $programs[$program], $coeField, $gwa, $year, $daysAgo);
            $this->transition($application, 'shortlisted', at: now()->subDays($daysAgo - 4));
            app(AwardService::class)->create($application->fresh(), $staff);
        }

        // --- Shortlisted, awaiting award -------------------------------------

        foreach ([
            ['2024-00248', 'Morales', 'Carlo', 'male', 'BSED', 99.75, 1, 12],
            ['2024-00291', 'Valdez', 'Trisha', 'female', 'BEED', 99.10, 1, 11],
            ['2024-00330', 'Ignacio', 'Miguel', 'male', 'BSCRIM', 99.40, 1, 10],
        ] as [$id, $last, $first, $sex, $program, $gwa, $year, $daysAgo]) {
            $application = $this->apply($offering, $id, $last, $first, $sex, $programs[$program], $coeField, $gwa, $year, $daysAgo);
            $this->transition($application, 'shortlisted', at: now()->subDays($daysAgo - 3));
        }

        // --- Screening queue -----------------------------------------------

        foreach ([
            ['2024-00372', 'Lopez', 'Jasmine', 'female', 'BSHM', 99.20, 1, 3],
            ['2024-00415', 'Garcia', 'Ramon', 'male', 'BIT', 99.60, 1, 2],
            ['2024-00459', 'Soriano', 'Nicole', 'female', 'BSBA', 97.50, 1, 2], // fails the GWA rule
            ['2024-00501', 'Torres', 'Adrian', 'male', 'BSF', 99.05, 1, 1],
        ] as [$id, $last, $first, $sex, $program, $gwa, $year, $daysAgo]) {
            $this->apply($offering, $id, $last, $first, $sex, $programs[$program], $coeField, $gwa, $year, $daysAgo);
        }

        // An existing DOST applicant also applying here — shows the multi-application flag.
        $mark = Applicant::where('student_id', '2021-00204')->first();
        if ($mark) {
            $this->withCoe($this->submit($offering, $mark, $programs['BIT'], null, ['gwa' => 99.00, 'year_level' => 4], daysAgo: 1), $coeField);
        }
    }

    protected function apply(
        ProgramOffering $offering,
        string $studentId,
        string $lastName,
        string $firstName,
        string $sex,
        Program $program,
        $coeField,
        float $gwa,
        int $yearLevel,
        int $daysAgo,
    ): Application {
        $applicant = $this->applicant($studentId, $lastName, $firstName, strtolower($firstName.'.'.$lastName).'@example.test', $sex);
        $application = $this->submit($offering, $applicant, $program, null, ['gwa' => $gwa, 'year_level' => $yearLevel], daysAgo: $daysAgo);

        return $this->withCoe($application, $coeField);
    }

    /**
     * The offering's COE field is a text field, so the parent's file-based
     * submit() leaves it empty — fill it in and screen again so the
     * completeness check passes.
     */
    protected function withCoe(Application $application, $coeField): Application
    {
        if (! $coeField) {
            return $application;
        }

        $application->fieldResponses()->create([
            'offering_form_field_id' => $coeField->id,
            'value' => 'COE-'.$application->applicant->student_id,
        ]);

        app(ScreeningService::class)->screen($application->fresh());

        return $application->fresh();
    }
}
