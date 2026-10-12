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
use App\Services\OfferingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ScholarMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function award(array $offeringOverrides = [], array $scholarshipProgramOverrides = [], array $applicantOverrides = [], array $applicationOverrides = []): Award
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create(array_merge([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ], $scholarshipProgramOverrides));
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = ProgramOffering::create(array_merge([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
            'amount' => 5000,
        ], $offeringOverrides));

        $college = College::create(array_merge(['code' => 'CICS', 'name' => 'College of ICS'], $applicationOverrides['college'] ?? []));
        $applicant = Applicant::create(array_merge([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Reyes',
            'sex' => 'male', 'email' => 'juan@example.com',
        ], $applicantOverrides));
        $application = $applicant->applications()->create(array_merge([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'awarded', 'submitted_at' => now(),
        ], collect($applicationOverrides)->except('college')->all()));
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
        ]);
    }

    public function test_scholars_roster_shows_every_recorded_award_by_default(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award();

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->assertSeeText($award->applicant->last_name)
            ->assertSeeText($award->programOffering->scholarshipProgram->name)
            ->assertSeeText('5,000.00');
    }

    public function test_scholars_roster_can_be_filtered_by_college(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $cics = $this->award(applicantOverrides: ['last_name' => 'Santos'], applicationOverrides: ['college' => ['code' => 'CICS', 'name' => 'College of ICS']]);
        $ceng = $this->award(applicantOverrides: ['last_name' => 'Bautista'], applicationOverrides: ['college' => ['code' => 'CENG', 'name' => 'College of Engineering']]);

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->set('college_id', $ceng->application->college_id)
            ->assertSeeText('Bautista')
            ->assertDontSeeText('Santos');
    }

    public function test_scholars_roster_can_be_filtered_by_scholarship_name(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $dost = $this->award(scholarshipProgramOverrides: ['name' => 'DOST-SEI Fellowship'], applicantOverrides: ['last_name' => 'Santos']);
        $ched = $this->award(scholarshipProgramOverrides: ['name' => 'CHED Merit Grant'], applicantOverrides: ['last_name' => 'Bautista'], applicationOverrides: ['college' => ['code' => 'CENG', 'name' => 'College of Engineering']]);

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->set('scholarship_program_id', $ched->programOffering->scholarship_program_id)
            ->assertSeeText('Bautista')
            ->assertDontSeeText('Santos');
    }

    public function test_applicant_sees_their_award_read_only_on_the_tracking_dashboard(): void
    {
        $award = $this->award();

        $this->withSession(['tracking_application_id' => $award->application_id]);

        Livewire::test('public::track-dashboard', ['offering' => $award->programOffering])
            ->assertSeeText('5,000.00')
            ->assertDontSeeText('Accept award')
            ->assertDontSeeText('Apply for renewal');
    }

    public function test_applicant_sees_publish_instructions_only_once_results_are_published(): void
    {
        $award = $this->award();
        $this->withSession(['tracking_application_id' => $award->application_id]);

        Livewire::test('public::track-dashboard', ['offering' => $award->programOffering])
            ->assertDontSee('Claim your certificate at Room 204.');

        $award->programOffering->update([
            'results_published' => true,
            'publish_instructions' => 'Claim your certificate at Room 204.',
            'results_published_at' => now(),
        ]);

        Livewire::test('public::track-dashboard', ['offering' => $award->programOffering->fresh()])
            ->assertSee('Claim your certificate at Room 204.');
    }

    public function test_offering_service_refuses_to_publish_while_slots_remain(): void
    {
        $award = $this->award(); // total_slots: 5, only 1 awarded

        $this->expectException(\RuntimeException::class);

        app(OfferingService::class)->publishResults($award->programOffering, 'Claim at Room 204.');
    }

    public function test_offering_service_publishes_once_every_slot_is_awarded(): void
    {
        $award = $this->award(['total_slots' => 1]);

        app(OfferingService::class)->publishResults($award->programOffering, 'Claim at Room 204.');

        $award->programOffering->refresh();
        $this->assertTrue($award->programOffering->results_published);
        $this->assertSame('Claim at Room 204.', $award->programOffering->publish_instructions);
        $this->assertNotNull($award->programOffering->results_published_at);
    }

    public function test_offering_service_unpublish_keeps_instructions_for_reuse(): void
    {
        $award = $this->award(['total_slots' => 1]);
        app(OfferingService::class)->publishResults($award->programOffering, 'Claim at Room 204.');

        app(OfferingService::class)->unpublishResults($award->programOffering->fresh());

        $offering = $award->programOffering->fresh();
        $this->assertFalse($offering->results_published);
        $this->assertSame('Claim at Room 204.', $offering->publish_instructions);
    }

    public function test_publish_button_is_disabled_while_slots_remain(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award(); // total_slots: 5, only 1 awarded

        Livewire::withQueryParams(['program' => $award->programOffering->scholarship_program_id, 'group' => $award->program_offering_id])->actingAs($staff)
            ->test('staff::grantees.index')
            ->assertSeeText('1/5 slots awarded')
            ->assertSeeHtml('disabled');
    }

    public function test_staff_can_publish_results_once_slots_are_full(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award(['total_slots' => 1]);

        Livewire::withQueryParams(['program' => $award->programOffering->scholarship_program_id, 'group' => $award->program_offering_id])->actingAs($staff)
            ->test('staff::grantees.index')
            ->set('instructions', 'Claim your certificate at Room 204.')
            ->call('publish')
            ->assertDispatched('notice', title: 'Results published');

        $this->assertDatabaseHas('program_offerings', [
            'id' => $award->program_offering_id,
            'results_published' => 1,
            'publish_instructions' => 'Claim your certificate at Room 204.',
        ]);
    }

    public function test_staff_can_unpublish_results(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award(['total_slots' => 1]);
        app(OfferingService::class)->publishResults($award->programOffering, 'Claim at Room 204.');

        Livewire::withQueryParams(['program' => $award->programOffering->scholarship_program_id, 'group' => $award->program_offering_id])->actingAs($staff)
            ->test('staff::grantees.index')
            ->call('unpublish')
            ->assertDispatched('notice', title: 'Results unpublished');

        $this->assertDatabaseHas('program_offerings', [
            'id' => $award->program_offering_id,
            'results_published' => 0,
        ]);
    }

    public function test_grantees_can_be_searched_by_name_or_student_id(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $this->award(applicantOverrides: ['last_name' => 'Santos', 'student_id' => '21-00001']);
        $this->award(applicantOverrides: ['last_name' => 'Bautista', 'student_id' => '21-00002'], applicationOverrides: ['college' => ['code' => 'CENG', 'name' => 'College of Engineering']]);

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->set('search', '21-00002')
            ->assertSeeText('Bautista')
            ->assertDontSeeText('Santos')
            ->set('search', 'santos')
            ->assertSeeText('Santos')
            ->assertDontSeeText('Bautista');
    }

    public function test_a_renewed_grantee_is_listed_once_at_the_latest_offering_and_the_group_opens_on_it(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award(offeringOverrides: ['name' => 'Batch 1']);
        $first = $award->programOffering;
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::Second,
            'start_date' => '2027-01-11', 'end_date' => '2027-05-28', 'is_current' => false,
        ]);
        $renewalOffering = ProgramOffering::create([
            'scholarship_program_id' => $first->scholarship_program_id, 'renews_offering_id' => $first->id,
            'academic_term_id' => $term->id, 'campus_id' => $first->campus_id, 'name' => '2nd sem',
            'opens_at' => '2027-01-11 08:00', 'closes_at' => '2027-02-11 17:00', 'total_slots' => 0,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => $first->category, 'amount' => 5000,
        ]);
        $renewal = $award->applicant->applications()->create([
            'access_token' => str_repeat('b', 64), 'program_offering_id' => $renewalOffering->id, 'kind' => 'renewal',
            'renewed_from_award_id' => $award->id, 'college_id' => $award->application->college_id,
            'year_level' => 3, 'status' => 'awarded', 'submitted_at' => now(),
        ]);
        Award::create([
            'application_id' => $renewal->id, 'applicant_id' => $award->applicant_id, 'program_offering_id' => $renewalOffering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
        ]);

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->assertSeeHtml('DOST-SEI Fellowship &middot; 2nd sem')
            ->assertDontSeeHtml('DOST-SEI Fellowship &middot; Batch 1')
            ->set('scholarship_program_id', $first->scholarship_program_id)
            ->set('group_id', $first->id)
            ->assertSeeHtml('DOST-SEI Fellowship &middot; 2nd sem')
            ->set('offering_id', $first->id)
            ->assertSeeHtml('DOST-SEI Fellowship &middot; Batch 1');
    }
}
