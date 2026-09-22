<?php

namespace Tests\Feature\Staff;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicantWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A shortlisted application — mirroring an applicant who cleared
     * screening and is ready for an award decision.
     */
    private function shortlistedApplication(): Application
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 1,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000,
        ]);

        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);

        $application = $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'shortlisted',
            'submitted_at' => now(),
        ]);

        return $application->load('programOffering');
    }

    public function test_staff_can_approve_an_award_from_the_applicant_page(): void
    {
        Mail::fake();

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->shortlistedApplication();

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('startAward')
            ->call('confirmAward')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('awards', ['application_id' => $application->id, 'status' => 'reserved']);
        $this->assertSame('approved', $application->fresh()->status->value);
    }
}
