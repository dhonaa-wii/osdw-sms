<?php

namespace Tests\Feature;

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
use App\Services\AwardService;
use App\Services\ScholarshipHoldingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NationalScholarshipRuleTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    private College $college;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $this->college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $this->applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
    }

    private function offering(FundingSource $source): ProgramOffering
    {
        $provider = Provider::create(['name' => $source->label().' '.Str::random(4), 'funding_source' => $source, 'is_active' => true]);
        $program = ScholarshipProgram::create(['name' => $source->label().' Scholarship '.Str::random(4), 'provider_id' => $provider->id]);

        return ProgramOffering::create([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $this->term->id,
            'campus_id' => Campus::firstOrCreate(['name' => 'Aparri'])->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 5, 'amount' => 5000,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);
    }

    private function application(ProgramOffering $offering, string $status = 'shortlisted'): Application
    {
        return $this->applicant->applications()->create([
            'access_token' => Str::random(64),
            'program_offering_id' => $offering->id, 'college_id' => $this->college->id,
            'year_level' => 2, 'status' => $status, 'submitted_at' => now(),
        ]);
    }

    private function award(Application $application): void
    {
        app(AwardService::class)->create($application, User::factory()->create(['role' => UserRole::OsdwStaff]));
    }

    public function test_a_national_grantee_cannot_apply_to_another_national_scholarship(): void
    {
        $this->award($this->application($this->offering(FundingSource::NationalGovernment)));
        $holdings = app(ScholarshipHoldingService::class);

        $this->assertNotNull($holdings->applyBlocker($this->applicant, $this->offering(FundingSource::NationalNonGovernment)));
        $this->assertNull($holdings->applyBlocker($this->applicant, $this->offering(FundingSource::LocalGovernment)));
        $this->assertNull($holdings->applyBlocker($this->applicant, $this->offering(FundingSource::LocalNonGovernment)));
    }

    public function test_a_local_grantee_can_apply_anywhere(): void
    {
        $this->award($this->application($this->offering(FundingSource::LocalGovernment)));
        $holdings = app(ScholarshipHoldingService::class);

        $this->assertNull($holdings->applyBlocker($this->applicant, $this->offering(FundingSource::NationalGovernment)));
        $this->assertNull($holdings->applyBlocker($this->applicant, $this->offering(FundingSource::LocalNonGovernment)));
    }

    public function test_awarding_one_national_application_blocks_the_other(): void
    {
        $first = $this->application($this->offering(FundingSource::NationalGovernment));
        $second = $this->application($this->offering(FundingSource::NationalNonGovernment));
        $holdings = app(ScholarshipHoldingService::class);

        $this->assertNull($holdings->awardBlocker($second));

        $this->award($first);

        $this->assertNotNull($holdings->awardBlocker($second->fresh()));
        $this->expectException(\RuntimeException::class);
        $this->award($second->fresh());
    }

    public function test_the_staff_review_page_shows_scholarships_held_elsewhere(): void
    {
        $this->award($this->application($this->offering(FundingSource::LocalGovernment)));
        $current = $this->application($this->offering(FundingSource::NationalGovernment), 'screening');

        $this->actingAs(User::factory()->create(['role' => UserRole::OsdwStaff]))
            ->get(route('staff.applications.review', $current))
            ->assertOk()
            ->assertSee('Scholarships held')
            ->assertSee('Local Government');
    }
}
