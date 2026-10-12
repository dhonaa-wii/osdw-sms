<?php

namespace Tests\Feature\Public;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_is_noindexed(): void
    {
        $this->get(route('results.index'))->assertSee('noindex, nofollow', false);
    }

    public function test_track_dashboard_is_noindexed(): void
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
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-000001', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $application = $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $this->withSession(['tracking_application_id' => $application->id])
            ->get(route('programs.track.dashboard', $offering))
            ->assertSee('noindex, nofollow', false);
    }

    public function test_browse_page_is_indexable(): void
    {
        $this->get(route('programs.index'))->assertDontSee('noindex', false);
    }
}
