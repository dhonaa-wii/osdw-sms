<?php

namespace Tests\Feature\Staff;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\OfferingFormField;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function programOffering(AcademicTerm $term, Campus $campus, array $overrides = []): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ]);

        return ProgramOffering::create(array_merge([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(30), 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
            'amount' => 5000,
        ], $overrides));
    }

    private function application(ProgramOffering $offering, array $overrides = []): Application
    {
        $college = College::create(['code' => 'C'.Str::random(4), 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);

        return $applicant->applications()->create(array_merge([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'awarded', 'submitted_at' => now(),
        ], $overrides));
    }

    public function test_staff_dashboard_shows_current_term_funnel_counts(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = $this->programOffering($term, $campus);

        $this->application($offering, ['status' => 'awarded']);
        $this->application($offering, ['status' => 'screening', 'screened_at' => now()]);
        $this->application($offering, ['status' => 'shortlisted', 'screened_at' => now()]);

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Received')
            ->assertSeeText('3')
            ->assertSeeText('Screened')
            ->assertSeeText('2')
            ->assertSeeText('Shortlisted')
            ->assertSeeText('1');
    }

    public function test_staff_dashboard_shows_pending_tasks_and_slot_utilization(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = $this->programOffering($term, $campus, ['total_slots' => 10]);

        $application = $this->application($offering, ['status' => 'screening']);

        $formField = OfferingFormField::create([
            'program_offering_id' => $offering->id, 'field_type' => 'file',
            'label' => 'Certificate of Registration', 'is_required' => true, 'sort_order' => 1,
        ]);
        ApplicationDocument::create([
            'application_id' => $application->id, 'offering_form_field_id' => $formField->id,
            'file_path' => 'documents/cor.pdf', 'original_name' => 'cor.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 1024, 'status' => 'pending',
        ]);

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $awardedApplication = $this->application($offering, ['status' => 'awarded']);
        Award::create([
            'application_id' => $awardedApplication->id, 'applicant_id' => $awardedApplication->applicant_id,
            'program_offering_id' => $offering->id, 'academic_term_id' => $term->id, 'amount' => 5000,
            'awarded_by' => $staff->id, 'awarded_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('DOST-SEI Fellowship')
            ->assertSeeText('Applications to screen')
            ->assertSeeText('Documents to verify')
            ->assertSeeText('1/10')
            ->assertSeeText('Juan Dela Cruz');
    }

    public function test_admin_dashboard_shows_operational_and_executive_cards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Slots filled this term')
            ->assertSeeText('Pending tasks')
            ->assertSeeText('Budget utilization')
            ->assertSeeText('Records');
    }

    public function test_staff_dashboard_does_not_show_executive_summary(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Budget utilization')
            ->assertDontSee('Slots across terms');
    }

    public function test_admin_dashboard_shows_budget_utilization_for_programs_with_a_budget(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = $this->programOffering($term, $campus, ['amount' => 5000]);
        $provider = $offering->scholarshipProgram->provider;
        $provider->update(['allocated_budget' => 20000]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $application = $this->application($offering, ['status' => 'awarded']);
        Award::create([
            'application_id' => $application->id, 'applicant_id' => $application->applicant_id,
            'program_offering_id' => $offering->id, 'academic_term_id' => $term->id, 'amount' => 5000,
            'awarded_by' => $admin->id, 'awarded_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Budget utilization')
            ->assertSeeText($provider->name)
            ->assertSeeText('5,000.00')
            ->assertSeeText('20,000.00');
    }

    public function test_search_finds_programs_by_name_or_provider_and_providers_by_name(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $offering = $this->programOffering($term, Campus::create(['name' => 'Aparri']));
        $provider = $offering->scholarshipProgram->provider;

        $this->actingAs(User::factory()->create(['role' => UserRole::OsdwStaff]));

        Livewire::test('staff::dashboard')
            ->set('search', 'Fellowship')
            ->assertSeeText('DOST-SEI Fellowship')
            ->assertSee(route('staff.applications.workspace', $offering))
            ->set('search', 'DOST-SEI')
            ->assertSeeText('DOST-SEI Fellowship')
            ->assertSeeText($provider->name)
            ->set('search', 'Nothing like this')
            ->assertSeeText('No programs or providers match');
    }

    public function test_admin_dashboard_shows_term_trends(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $this->programOffering($term, $campus, ['total_slots' => 10]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Slots across terms')
            ->assertSeeText('2026-2027');
    }
}
