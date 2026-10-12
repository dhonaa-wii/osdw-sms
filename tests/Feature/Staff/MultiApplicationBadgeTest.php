<?php

namespace Tests\Feature\Staff;

use App\Enums\DegreeLevel;
use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MultiApplicationBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function offeringAndCollegeAndProgram(): array
    {
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment]);
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
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $program = Program::create([
            'college_id' => $college->id, 'code' => 'P'.Str::random(3), 'name' => 'BS Information Technology',
            'degree_level' => DegreeLevel::Undergraduate,
        ]);

        return [$offering, $college, $program, $term];
    }

    private function applicationFor(Applicant $applicant, ProgramOffering $offering, College $college, Program $program, string $status): Application
    {
        return $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => $status, 'submitted_at' => now(),
        ]);
    }

    public function test_awarded_application_yields_red_badge(): void
    {
        [$offering, $college, $program, $term] = $this->offeringAndCollegeAndProgram();
        $applicant = Applicant::create(['student_id' => 'SID-'.Str::random(6), 'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'female', 'email' => Str::random(6).'@example.com']);
        $application = $this->applicationFor($applicant, $offering, $college, $program, 'awarded');
        $staff = User::factory()->create();
        Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
        ]);

        $badge = $application->fresh()->outcomeBadge();

        $this->assertSame('crimson-600', $badge['color']);
    }

    public function test_screening_without_an_award_yields_amber_badge(): void
    {
        [$offering, $college, $program] = $this->offeringAndCollegeAndProgram();
        $applicant = Applicant::create(['student_id' => 'SID-'.Str::random(6), 'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'female', 'email' => Str::random(6).'@example.com']);
        $application = $this->applicationFor($applicant, $offering, $college, $program, 'screening');

        $this->assertSame('amber-600', $application->outcomeBadge()['color']);
    }

    public function test_denied_application_yields_gray_badge(): void
    {
        [$offering, $college, $program] = $this->offeringAndCollegeAndProgram();
        $applicant = Applicant::create(['student_id' => 'SID-'.Str::random(6), 'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'female', 'email' => Str::random(6).'@example.com']);
        $application = $this->applicationFor($applicant, $offering, $college, $program, 'denied');

        $this->assertSame('mist', $application->outcomeBadge()['color']);
    }

    public function test_review_page_shows_the_badge_for_other_applications(): void
    {
        [$offering, $college, $program] = $this->offeringAndCollegeAndProgram();
        [$otherOffering] = $this->offeringAndCollegeAndProgram();
        $applicant = Applicant::create(['student_id' => 'SID-'.Str::random(6), 'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'female', 'email' => Str::random(6).'@example.com']);
        $application = $this->applicationFor($applicant, $offering, $college, $program, 'screening');
        $this->applicationFor($applicant, $otherOffering, $college, $program, 'denied');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->assertSee('History');
    }

    public function test_workspace_queue_shows_other_application_count(): void
    {
        [$offering, $college, $program] = $this->offeringAndCollegeAndProgram();
        [$otherOffering] = $this->offeringAndCollegeAndProgram();
        $applicant = Applicant::create(['student_id' => 'SID-'.Str::random(6), 'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'female', 'email' => Str::random(6).'@example.com']);
        $this->applicationFor($applicant, $offering, $college, $program, 'screening');
        $this->applicationFor($applicant, $otherOffering, $college, $program, 'denied');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->assertSee('1 other');
    }
}
