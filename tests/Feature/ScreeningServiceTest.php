<?php

namespace Tests\Feature;

use App\Enums\DegreeLevel;
use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Services\ScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScreeningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function offeringWithGwaRule(): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);
        $offering->eligibilityRules()->create([
            'field' => 'gwa', 'operator' => 'gte', 'value' => '80', 'message' => 'GWA must be 80 or better.',
        ]);

        return $offering;
    }

    private function applicationFor(ProgramOffering $offering, ?string $gwa): Application
    {
        $college = College::create(['code' => 'CICS-'.Str::random(3), 'name' => 'College of ICS']);
        Program::create([
            'college_id' => $college->id, 'code' => 'BSIT-'.Str::random(3), 'name' => 'BS Information Technology',
            'degree_level' => DegreeLevel::Undergraduate,
        ]);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);

        return $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'gwa' => $gwa, 'status' => 'submitted', 'submitted_at' => now(),
        ]);
    }

    public function test_a_failed_rule_is_flagged_but_stays_in_screening_for_staff_review(): void
    {
        $offering = $this->offeringWithGwaRule();
        $application = $this->applicationFor($offering, '65.00');

        app(ScreeningService::class)->screen($application);

        $application->refresh();
        $this->assertSame('screening', $application->status->value);
        $this->assertDatabaseHas('application_screenings', [
            'application_id' => $application->id, 'check_type' => 'eligibility', 'passed' => 0,
        ]);
    }

    public function test_application_advances_to_screening_when_all_rules_pass(): void
    {
        $offering = $this->offeringWithGwaRule();
        $application = $this->applicationFor($offering, '90.00');

        app(ScreeningService::class)->screen($application);

        $application->refresh();
        $this->assertSame('screening', $application->status->value);
    }

    public function test_missing_gwa_fails_strictly_during_screening(): void
    {
        $offering = $this->offeringWithGwaRule();
        $application = $this->applicationFor($offering, null);

        app(ScreeningService::class)->screen($application);

        $application->refresh();
        $this->assertSame('screening', $application->status->value);
        $this->assertDatabaseHas('application_screenings', [
            'application_id' => $application->id, 'check_type' => 'eligibility', 'passed' => 0,
        ]);
    }

    public function test_missing_required_document_fails_completeness_check(): void
    {
        $offering = $this->offeringWithGwaRule();
        $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);
        $application = $this->applicationFor($offering, '90.00');

        app(ScreeningService::class)->screen($application);

        $application->refresh();
        $this->assertSame('screening', $application->status->value);
        $this->assertDatabaseHas('application_screenings', [
            'application_id' => $application->id, 'check_type' => 'completeness', 'passed' => 0,
        ]);
    }

    public function test_duplicate_notice_is_recorded_but_never_fails_screening(): void
    {
        $offering = $this->offeringWithGwaRule();
        $application = $this->applicationFor($offering, '90.00');
        $otherOffering = $this->offeringWithGwaRule();
        $application->applicant->applications()->create([
            'access_token' => str_repeat('b', 64),
            'program_offering_id' => $otherOffering->id,
            'college_id' => $application->college_id, 'year_level' => 2, 'gwa' => '92.00',
            'status' => 'submitted', 'submitted_at' => now(),
        ]);

        app(ScreeningService::class)->screen($application);

        $application->refresh();
        $this->assertSame('screening', $application->status->value);
        $this->assertDatabaseHas('application_screenings', [
            'application_id' => $application->id, 'check_type' => 'duplicate', 'passed' => 1,
        ]);
    }
}
