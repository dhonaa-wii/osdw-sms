<?php

namespace Tests\Feature\Staff;

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
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ScholarMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function activeAward(array $offeringOverrides = []): Award
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
        $offering = ProgramOffering::create(array_merge([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000,
        ], $offeringOverrides));

        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $application = $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'accepted', 'submitted_at' => now(),
        ]);
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
            'acceptance_status' => 'accepted', 'accepted_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_staff_can_view_the_scholars_picker(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->activeAward();

        $this->actingAs($staff)
            ->get(route('staff.scholars.index'))
            ->assertOk()
            ->assertSee($award->programOffering->scholarshipProgram->name);
    }

    public function test_applicant_can_submit_grades_for_the_current_term(): void
    {
        $award = $this->activeAward();

        $this->withSession(['tracking_applicant_id' => $award->applicant_id]);

        Livewire::test('public::track-dashboard')
            ->set("gradeGwa.{$award->id}", '90.00')
            ->set("gradeUnits.{$award->id}", '18')
            ->call('submitGrades', $award->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scholar_terms', [
            'award_id' => $award->id, 'applicant_id' => $award->applicant_id, 'gwa' => 90.00, 'units_enrolled' => 18,
        ]);
    }

    public function test_applicant_can_apply_for_renewal_before_submitting_grades(): void
    {
        $award = $this->activeAward();
        $term = $award->programOffering->academicTerm;

        $this->withSession(['tracking_applicant_id' => $award->applicant_id]);

        Livewire::test('public::track-dashboard')
            ->call('applyForRenewal', $award->id)
            ->assertSeeText('Submit grades');

        $this->assertDatabaseHas('scholar_terms', [
            'award_id' => $award->id, 'academic_term_id' => $term->id, 'gwa' => null,
        ]);
    }

    public function test_applying_for_renewal_twice_does_not_duplicate_the_term_record(): void
    {
        $award = $this->activeAward();

        $this->withSession(['tracking_applicant_id' => $award->applicant_id]);

        Livewire::test('public::track-dashboard')->call('applyForRenewal', $award->id);
        Livewire::test('public::track-dashboard')->call('applyForRenewal', $award->id);

        $this->assertSame(1, $award->scholarTerms()->count());
    }

    public function test_dashboard_shows_apply_for_renewal_before_any_term_record_exists(): void
    {
        $award = $this->activeAward();

        $this->withSession(['tracking_applicant_id' => $award->applicant_id]);

        Livewire::test('public::track-dashboard')->assertSeeText('Apply for renewal');
    }

    public function test_scholars_roster_shows_pending_renewal_without_running_retention_evaluation(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->activeAward();
        $award->programOffering->retentionRules()->create([
            'field' => 'gwa', 'operator' => 'gte', 'value' => '80', 'message' => 'GWA fell below the retention threshold.',
        ]);
        $term = $award->programOffering->academicTerm;
        $award->scholarTerms()->create([
            'applicant_id' => $award->applicant_id, 'academic_term_id' => $term->id, 'status' => 'active',
        ]);

        Livewire::actingAs($staff)
            ->test('staff::scholars.workspace', ['offering' => $award->programOffering])
            ->assertSeeText('Applied for renewal')
            ->assertDontSeeText('GWA fell below the retention threshold.');
    }

    public function test_retention_evaluation_flags_a_scholar_who_fails_a_configured_rule(): void
    {
        $award = $this->activeAward();
        $award->programOffering->retentionRules()->create([
            'field' => 'gwa', 'operator' => 'gte', 'value' => '80', 'message' => 'GWA fell below the retention threshold.',
        ]);
        $term = $award->programOffering->academicTerm;
        $scholarTerm = $award->scholarTerms()->create([
            'applicant_id' => $award->applicant_id, 'academic_term_id' => $term->id,
            'gwa' => 65.00, 'units_enrolled' => 18, 'has_failing_grade' => false, 'status' => 'active',
        ]);

        $evaluation = app(RetentionService::class)->evaluate($scholarTerm);

        $this->assertFalse($evaluation->first()['passed']);
    }

    public function test_offering_without_retention_rules_evaluates_to_empty(): void
    {
        $award = $this->activeAward();
        $term = $award->programOffering->academicTerm;
        $scholarTerm = $award->scholarTerms()->create([
            'applicant_id' => $award->applicant_id, 'academic_term_id' => $term->id,
            'gwa' => 78.00, 'units_enrolled' => 18, 'has_failing_grade' => false, 'status' => 'active',
        ]);

        $evaluation = app(RetentionService::class)->evaluate($scholarTerm);

        $this->assertTrue($evaluation->isEmpty());
    }

    public function test_staff_can_set_scholar_to_probation_with_reason(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->activeAward();
        $term = $award->programOffering->academicTerm;
        $scholarTerm = $award->scholarTerms()->create([
            'applicant_id' => $award->applicant_id, 'academic_term_id' => $term->id,
            'gwa' => 78.00, 'units_enrolled' => 18, 'has_failing_grade' => false, 'status' => 'active',
        ]);

        Livewire::actingAs($staff)
            ->test('staff::scholars.workspace', ['offering' => $award->programOffering])
            ->call('startStatus', $scholarTerm->id)
            ->call('setStatus', 'probation')
            ->assertHasErrors(['statusReason']);

        Livewire::actingAs($staff)
            ->test('staff::scholars.workspace', ['offering' => $award->programOffering])
            ->call('startStatus', $scholarTerm->id)
            ->set('statusReason', 'GWA fell below the retention threshold this term.')
            ->call('setStatus', 'probation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scholar_terms', ['id' => $scholarTerm->id, 'status' => 'probation']);
    }

    public function test_terminating_a_scholar_also_terminates_the_award(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->activeAward();
        $term = $award->programOffering->academicTerm;
        $scholarTerm = $award->scholarTerms()->create([
            'applicant_id' => $award->applicant_id, 'academic_term_id' => $term->id,
            'gwa' => 78.00, 'units_enrolled' => 18, 'has_failing_grade' => true, 'status' => 'active',
        ]);

        Livewire::actingAs($staff)
            ->test('staff::scholars.workspace', ['offering' => $award->programOffering])
            ->call('startStatus', $scholarTerm->id)
            ->set('statusReason', 'Two consecutive terms of failing grades.')
            ->call('setStatus', 'terminated')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scholar_terms', ['id' => $scholarTerm->id, 'status' => 'terminated']);
        $this->assertDatabaseHas('awards', ['id' => $award->id, 'status' => 'terminated']);
    }
}
