<?php

namespace Tests\Feature\Admin;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderViewTest extends TestCase
{
    use RefreshDatabase;

    private function award(ScholarshipProgram $program, AcademicTerm $term, Campus $campus, College $college, string $status, float $amount): Award
    {
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => $amount,
        ]);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);
        $application = $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'accepted', 'submitted_at' => now(),
        ]);
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => $amount, 'awarded_by' => $staff->id, 'awarded_at' => now(),
            'acceptance_status' => 'accepted', 'accepted_at' => now(),
            'status' => $status,
        ]);
    }

    public function test_non_admin_cannot_access_provider_view_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);

        $this->actingAs($staff)->get("/admin/providers/{$provider->id}/view")->assertForbidden();
    }

    public function test_provider_view_totals_only_active_and_completed_awards_across_terms(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        $termOne = AcademicTerm::create([
            'school_year' => '2025-2026', 'semester' => Semester::First,
            'start_date' => '2025-08-03', 'end_date' => '2025-12-18', 'is_current' => false,
        ]);
        $termTwo = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);

        $this->award($program, $termOne, $campus, $college, 'active', 5000);
        $this->award($program, $termTwo, $campus, $college, 'completed', 6000);
        $this->award($program, $termTwo, $campus, $college, 'reserved', 6000);
        $this->award($program, $termTwo, $campus, $college, 'terminated', 6000);

        Livewire::actingAs($admin)
            ->test('admin::master-data.provider-view', ['provider' => $provider])
            ->assertSee('DOST-SEI Fellowship')
            ->assertSee(number_format(11000, 2))
            ->assertSee('2025-2026')
            ->assertSee('2026-2027')
            ->assertViewHas('grandTotal', fn ($value) => (float) $value === 11000.0)
            ->assertViewHas('totalScholars', 2);
    }

    public function test_provider_view_shows_programs_with_no_awards_yet(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $provider = Provider::create(['name' => 'CSUA Alumni Association', 'funding_source' => FundingSource::LocalNonGovernment, 'is_active' => true]);
        ScholarshipProgram::create([
            'name' => 'CSUA Alumni Grant', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.provider-view', ['provider' => $provider])
            ->assertSee('CSUA Alumni Grant')
            ->assertSee('No awards recorded for this program yet')
            ->assertViewHas('grandTotal', fn ($value) => (float) $value === 0.0);
    }
}
