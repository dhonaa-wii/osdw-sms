<?php

namespace Tests\Feature\Staff;

use App\Enums\DegreeLevel;
use App\Enums\FundingSource;
use App\Enums\RequirementStage;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\SubmissionMode;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Campus;
use App\Models\College;
use App\Models\OfferingAdditionalRequirement;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AwardService;
use App\Services\OfferingRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OfferingRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function offering(): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create(['name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);

        return ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'name' => 'Batch 1',
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::NonAcademic,
            'amount' => 5000,
            'shortlist_requirements_opened_at' => now(),
            'award_requirements_opened_at' => now(),
        ]);
    }

    private function application(ProgramOffering $offering, string $status = 'shortlisted'): Application
    {
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'College of ICS']);
        Program::firstOrCreate(['code' => 'BSIT'], [
            'college_id' => $college->id, 'name' => 'BS Information Technology', 'degree_level' => DegreeLevel::Undergraduate,
        ]);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);

        return $applicant->applications()->create([
            'access_token' => Str::random(64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => $status, 'submitted_at' => now(),
        ]);
    }

    private function requirement(ProgramOffering $offering, RequirementStage $stage, SubmissionMode $mode): OfferingAdditionalRequirement
    {
        return $offering->additionalRequirements()->create([
            'stage' => $stage, 'label' => 'Good Moral Certificate', 'submission_mode' => $mode,
            'accepted_formats' => $mode->needsUpload() ? 'pdf' : null,
            'max_size_mb' => $mode->needsUpload() ? 5 : null,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_adds_a_requirement_from_the_requirements_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->offering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('openSection', 'additional')
            ->call('addRequirement', 'shortlist')
            ->set('label', 'Barangay Clearance')
            ->set('submission_mode', 'both')
            ->set('accepted_formats', 'pdf, jpg')
            ->set('max_size_mb', '3')
            ->call('saveRequirement')
            ->assertHasNoErrors()
            ->assertSee('Barangay Clearance');

        $requirement = $offering->additionalRequirements()->sole();
        $this->assertSame(RequirementStage::Shortlist, $requirement->stage);
        $this->assertSame(SubmissionMode::Both, $requirement->submission_mode);
        $this->assertSame('pdf,jpg', $requirement->accepted_formats);
    }

    public function test_a_requirement_needs_a_stage_and_submission_mode(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $this->offering()])
            ->call('addRequirement')
            ->set('label', 'Barangay Clearance')
            ->call('saveRequirement')
            ->assertHasErrors(['stage', 'submission_mode']);
    }

    public function test_interview_details_are_saved_on_the_offering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->offering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('openSection', 'interview')
            ->set('has_interview', true)
            ->call('saveInterview')
            ->assertHasErrors(['interview_at', 'interview_venue'])
            ->set('interview_at', '2026-10-05T09:00')
            ->set('interview_venue', 'OSDW Conference Room')
            ->set('interview_instructions', 'Bring your school ID.')
            ->call('saveInterview')
            ->assertHasNoErrors();

        $offering->refresh();
        $this->assertTrue($offering->hasScheduledInterview());
        $this->assertSame('OSDW Conference Room', $offering->interview_venue);
    }

    public function test_award_is_blocked_until_shortlist_requirements_are_complied(): void
    {
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Physical);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        try {
            app(AwardService::class)->create($application, $staff);
            $this->fail('Award should be blocked while a shortlist requirement is outstanding.');
        } catch (\RuntimeException) {
            $this->assertNull($application->fresh()->award);
        }

        app(OfferingRequirementService::class)->togglePhysicalReceived($application, $requirement, $staff);
        app(AwardService::class)->create($application->fresh(), $staff);

        $this->assertNotNull($application->fresh()->award);
    }

    public function test_award_stage_requirements_do_not_block_the_award(): void
    {
        $offering = $this->offering();
        $application = $this->application($offering);
        $this->requirement($offering, RequirementStage::Award, SubmissionMode::Upload);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        app(AwardService::class)->create($application, $staff);

        $this->assertNotNull($application->fresh()->award);
    }

    public function test_student_uploads_a_requirement_from_the_tracking_page(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Upload);

        Livewire::withQueryParams([])
            ->test('public::track-dashboard', ['offering' => $offering])
            ->assertForbidden();

        session(['tracking_application_id' => $application->id]);

        Livewire::test('public::track-dashboard', ['offering' => $offering])
            ->assertSee('Good Moral Certificate')
            ->assertSee('To submit')
            ->set("uploads.{$requirement->id}", UploadedFile::fake()->create('moral.pdf', 100, 'application/pdf'))
            ->call('submitRequirement', $requirement->id)
            ->assertHasNoErrors()
            ->assertSee('Under review');

        $submission = $application->additionalRequirementSubmissions()->sole();
        Storage::disk('local')->assertExists($submission->file_path);
        $this->assertFalse($submission->isComplied());

        app(OfferingRequirementService::class)->verify($submission, User::factory()->create(['role' => UserRole::OsdwStaff]));
        $this->assertTrue($submission->fresh()->isComplied());
    }

    public function test_wrong_file_type_is_rejected_with_a_clear_message(): void
    {
        Storage::fake('local');
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Upload);
        session(['tracking_application_id' => $application->id]);

        Livewire::test('public::track-dashboard', ['offering' => $offering])
            ->set("uploads.{$requirement->id}", UploadedFile::fake()->create('moral.docx', 100))
            ->call('submitRequirement', $requirement->id)
            ->assertHasErrors("uploads.{$requirement->id}");
    }

    public function test_tracking_page_shows_the_interview_to_shortlisted_applicants(): void
    {
        $offering = $this->offering();
        $offering->update([
            'has_interview' => true, 'interview_at' => '2026-10-05 09:00',
            'interview_venue' => 'OSDW Conference Room', 'interview_instructions' => 'Bring your school ID.',
        ]);
        $application = $this->application($offering);
        session(['tracking_application_id' => $application->id]);

        Livewire::test('public::track-dashboard', ['offering' => $offering])
            ->assertSee('Your interview')
            ->assertSee('OSDW Conference Room')
            ->assertSee('Bring your school ID.');

        $application->update(['status' => 'screening']);

        Livewire::test('public::track-dashboard', ['offering' => $offering])
            ->assertDontSee('Your interview');
    }

    public function test_shortlist_tab_shows_requirement_progress_and_holds_the_award(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Physical);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->assertSee('0/1')
            ->assertSee('outstanding')
            ->call('award', $application->id)
            ->assertDispatched('notice', title: 'Requirements outstanding');

        $this->assertNull($application->fresh()->award);
    }

    public function test_shortlist_requirements_stay_hidden_from_applicants_until_opened(): void
    {
        $offering = $this->offering();
        $offering->update(['shortlist_requirements_opened_at' => null, 'total_slots' => 2]);
        $application = $this->application($offering);
        $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Upload);
        session(['tracking_application_id' => $application->id]);

        Livewire::test('public::track-dashboard', ['offering' => $offering])
            ->assertDontSee('Good Moral Certificate')
            ->assertSee('OSDW will post the requirements for the award here.');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        // One shortlisted applicant for two slots: the shortlist is not full yet.
        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->assertSee('Shortlist 1 more applicant to open the shortlist requirements.')
            ->call('openShortlistRequirements')
            ->assertDispatched('notice', title: 'Couldn\'t open requirements');

        $this->assertNull($offering->fresh()->shortlist_requirements_opened_at);

        // The shortlist may hold more applicants than there are slots.
        $this->application($offering);
        $this->application($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('openShortlistRequirements')
            ->assertHasNoErrors()
            ->assertSee('Shortlist requirements opened');

        Livewire::test('public::track-dashboard', ['offering' => $offering->fresh()])
            ->assertSee('Good Moral Certificate');
    }

    public function test_disbursement_requirements_open_from_the_grantees_page_once_every_slot_is_awarded(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $offering->update(['award_requirements_opened_at' => null, 'total_slots' => 2]);
        $application = $this->application($offering);
        $this->requirement($offering, RequirementStage::Award, SubmissionMode::Upload);
        app(AwardService::class)->create($application, $staff);
        session(['tracking_application_id' => $application->id]);

        Livewire::test('public::track-dashboard', ['offering' => $offering])
            ->assertDontSee('Good Moral Certificate');

        $grantees = fn () => Livewire::withQueryParams(['program' => $offering->scholarship_program_id, 'group' => $offering->id])
            ->actingAs($staff)
            ->test('staff::grantees.index');

        $grantees()
            ->assertSeeText('1 open')
            ->call('openAwardRequirements')
            ->assertDispatched('notice', title: 'Couldn\'t open requirements');

        app(AwardService::class)->create($this->application($offering), $staff);

        $grantees()
            ->call('openAwardRequirements')
            ->assertHasNoErrors()
            ->assertDispatched('notice', title: 'Disbursement requirements opened');

        Livewire::test('public::track-dashboard', ['offering' => $offering->fresh()])
            ->assertSee('Good Moral Certificate');
    }

    public function test_shortlist_tab_shows_the_scheduled_interview(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $offering->update(['has_interview' => true, 'interview_at' => '2026-09-20 09:00', 'interview_venue' => 'OSDW Conference Room']);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->assertSee('OSDW Conference Room')
            ->assertDontSee('Schedule interview');

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->assertSee('Edit interview');
    }

    public function test_grantees_roster_shows_disbursement_requirement_progress(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $this->requirement($offering, RequirementStage::Award, SubmissionMode::Physical);
        app(AwardService::class)->create($application, $staff);

        $this->actingAs($staff)
            ->get(route('staff.grantees.index'))
            ->assertOk()
            ->assertSee('Disbursement requirements')
            ->assertSee('0/1');
    }

    public function test_staff_verify_and_return_uploads_on_the_review_page(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Upload);
        $submission = app(OfferingRequirementService::class)->submitUpload(
            $application, $requirement, UploadedFile::fake()->create('moral.pdf', 100, 'application/pdf')
        );

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->assertSee('Shortlist requirements')
            ->call('rejectRequirement', $submission->id, '')
            ->assertDispatched('notice', title: 'Reason needed')
            ->call('rejectRequirement', $submission->id, 'The signature is missing.')
            ->assertHasNoErrors();

        $this->assertSame('rejected', $submission->fresh()->status->value);

        $submission = app(OfferingRequirementService::class)->submitUpload(
            $application, $requirement, UploadedFile::fake()->create('moral-signed.pdf', 100, 'application/pdf')
        );

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('verifyRequirement', $submission->id);

        $this->assertTrue($submission->fresh()->isComplied());
    }

    public function test_staff_verify_and_return_disbursement_uploads_from_the_grantees_page(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Award, SubmissionMode::Upload);
        $award = app(AwardService::class)->create($application, $staff);
        $service = app(OfferingRequirementService::class);
        $submission = $service->submitUpload($application->fresh(), $requirement, UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'));

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->call('openDisbursement', $award->id)
            ->assertSee('contract.pdf')
            ->call('rejectRequirement', $submission->id, '')
            ->assertDispatched('notice', title: 'Reason needed')
            ->call('rejectRequirement', $submission->id, 'Sign page 2.')
            ->assertSee('Returned');

        $this->assertSame('rejected', $submission->fresh()->status->value);

        $resubmitted = $service->submitUpload($application->fresh(), $requirement, UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'));

        Livewire::actingAs($staff)
            ->test('staff::grantees.index')
            ->call('openDisbursement', $award->id)
            ->call('verifyRequirement', $resubmitted->id);

        $this->assertSame('verified', $resubmitted->fresh()->status->value);
    }

    public function test_a_disbursement_upload_under_review_cannot_be_replaced(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Award, SubmissionMode::Upload);
        app(AwardService::class)->create($application, $staff);
        $service = app(OfferingRequirementService::class);
        $service->submitUpload($application->fresh(), $requirement, UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'));

        $this->expectException(\RuntimeException::class);
        $service->submitUpload($application->fresh(), $requirement, UploadedFile::fake()->create('again.pdf', 100, 'application/pdf'));
    }

    public function test_grantee_view_page_shows_student_information_and_every_requirement(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $application->applicant->update(['mobile' => '09171234567', 'address' => 'Aparri, Cagayan']);
        $shortlistRequirement = $this->requirement($offering, RequirementStage::Shortlist, SubmissionMode::Physical);
        $offering->additionalRequirements()->create([
            'stage' => RequirementStage::Award, 'label' => 'Signed contract', 'submission_mode' => SubmissionMode::Physical, 'sort_order' => 1,
        ]);
        $application->additionalRequirementSubmissions()->create([
            'offering_additional_requirement_id' => $shortlistRequirement->id,
            'physical_received_at' => now(), 'physical_received_by' => $staff->id,
        ]);
        $award = app(AwardService::class)->create($application, $staff);

        $this->actingAs($staff)
            ->get(route('staff.grantees.show', $award))
            ->assertOk()
            ->assertSee('Student information')
            ->assertSee('09171234567')
            ->assertSee('Aparri, Cagayan')
            ->assertSee('Shortlist requirements')
            ->assertSee('Disbursement requirements')
            ->assertSee('Signed contract')
            ->assertDontSee('togglePhysicalRequirement', false);
    }

    public function test_a_grantee_is_disbursed_once_every_disbursement_requirement_is_complied(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $application = $this->application($offering);
        $requirement = $this->requirement($offering, RequirementStage::Award, SubmissionMode::Physical);
        $award = app(AwardService::class)->create($application, $staff);
        $student = User::factory()->create(['role' => UserRole::Student]);
        $application->applicant->update(['user_id' => $student->id]);

        Livewire::actingAs($staff)
            ->test('staff::grantees.show', ['award' => $award])
            ->call('disburse', $award->id)
            ->assertDispatched('notice', title: 'Couldn\'t disburse');
        $this->assertNull($award->fresh()->disbursed_at);

        app(OfferingRequirementService::class)->togglePhysicalReceived($application->fresh(), $requirement, $staff);

        Livewire::actingAs($staff)
            ->test('staff::grantees.show', ['award' => $award])
            ->call('disburse', $award->id)
            ->assertDispatched('notice', title: 'Grantee disbursed');

        $this->assertNotNull($award->fresh()->disbursed_at);
        $this->assertSame('Disbursed', $application->fresh()->statusLabel());
        $this->assertSame('awarded', $application->fresh()->status->value);
        $this->assertSame('Your scholarship has been disbursed', $student->notifications()->first()?->data['title']);
    }

    public function test_disburse_all_waits_for_every_slot_and_every_requirement(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->offering();
        $offering->update(['total_slots' => 2]);
        $requirement = $this->requirement($offering, RequirementStage::Award, SubmissionMode::Physical);
        $first = $this->application($offering);
        app(AwardService::class)->create($first, $staff);

        $grantees = fn () => Livewire::withQueryParams(['program' => $offering->scholarship_program_id, 'group' => $offering->id])
            ->actingAs($staff)
            ->test('staff::grantees.index');

        $grantees()->call('disburseAll')->assertDispatched('notice', title: 'Couldn\'t disburse', message: 'Award every slot first.');

        $second = $this->application($offering);
        app(AwardService::class)->create($second, $staff);
        app(OfferingRequirementService::class)->togglePhysicalReceived($first->fresh(), $requirement, $staff);

        $grantees()->call('disburseAll')->assertDispatched('notice', title: 'Couldn\'t disburse');

        app(OfferingRequirementService::class)->togglePhysicalReceived($second->fresh(), $requirement, $staff);

        $grantees()->call('disburseAll')->assertDispatched('notice', title: 'Grantees disbursed');
        $this->assertSame(0, $offering->awards()->whereNull('disbursed_at')->count());
        $grantees()->assertSee('Disbursed');
    }
}
