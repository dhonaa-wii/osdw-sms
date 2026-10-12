<?php

namespace Tests\Feature;

use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\RequirementStage;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\SubmissionMode;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\OfferingAdditionalRequirement;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AwardService;
use App\Services\RenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ScholarshipProgram $program;

    private AcademicTerm $secondTerm;

    private ProgramOffering $source;

    private ProgramOffering $target;

    private OfferingAdditionalRequirement $gradesRequirement;

    private OfferingAdditionalRequirement $enrolmentRequirement;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        $provider = Provider::create(['name' => 'CHED', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $this->program = ScholarshipProgram::create(['name' => 'CHED Merit', 'provider_id' => $provider->id]);
        $campus = Campus::create(['name' => 'Aparri']);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        $first = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => Semester::First, 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true]);
        $this->secondTerm = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => Semester::Second, 'start_date' => '2027-01-11', 'end_date' => '2027-05-28', 'is_current' => false]);

        $base = [
            'scholarship_program_id' => $this->program->id, 'campus_id' => $campus->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'amount' => 5000,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ];

        $this->source = ProgramOffering::create([...$base, 'academic_term_id' => $first->id, 'name' => 'Batch 1', 'total_slots' => 5]);
        $this->target = ProgramOffering::create([
            ...$base, 'academic_term_id' => $this->secondTerm->id, 'name' => 'Batch 1 renewal',
            'renews_offering_id' => $this->source->id, 'total_slots' => 0,
        ]);

        $this->gradesRequirement = $this->target->additionalRequirements()->create([
            'stage' => RequirementStage::Renewal, 'label' => 'Certificate of grades', 'submission_mode' => SubmissionMode::Upload,
            'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);
        $this->enrolmentRequirement = $this->target->additionalRequirements()->create([
            'stage' => RequirementStage::Renewal, 'label' => 'Certificate of registration', 'submission_mode' => SubmissionMode::Physical,
            'sort_order' => 2,
        ]);

        $applicant = Applicant::create([
            'student_id' => '2023-00123', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'birthdate' => '2003-05-14', 'email' => 'juan@example.com',
        ]);
        $sourceApplication = $applicant->applications()->create([
            'access_token' => str_repeat('a', 64), 'program_offering_id' => $this->source->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'awarded', 'submitted_at' => now(),
        ]);
        Award::create([
            'application_id' => $sourceApplication->id, 'applicant_id' => $applicant->id,
            'program_offering_id' => $this->source->id, 'academic_term_id' => $first->id,
            'amount' => 5000, 'awarded_by' => $this->admin->id, 'awarded_at' => now(),
        ]);
    }

    public function test_admin_opens_renewals_for_scholars_of_the_linked_offering(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin::program-configuration.offering-view', ['offering' => $this->target])
            ->assertSee('Open renewals for 1 scholar')
            ->call('openRenewals')
            ->assertSee('Opened 1 renewal');

        $renewal = $this->target->applications()->sole();
        $this->assertSame(ApplicationKind::Renewal, $renewal->kind);
        $this->assertSame(ApplicationStatus::Screening, $renewal->status);
        $this->assertSame('Under review', $renewal->statusLabel());

        // Running it again opens nothing new.
        $this->assertSame(0, app(RenewalService::class)->openRenewals($this->target, $this->admin));
    }

    public function test_an_offering_that_renews_nothing_has_no_scholars_due(): void
    {
        $this->assertTrue(app(RenewalService::class)->dueAwards($this->source)->isEmpty());
    }

    public function test_scholar_hands_in_requirements_and_staff_renew_on_the_renewals_page(): void
    {
        app(RenewalService::class)->openRenewals($this->target, $this->admin);
        $renewal = $this->target->applications()->sole();

        session(['tracking_application_id' => $renewal->id]);

        Livewire::test('public::track-dashboard', ['offering' => $this->target])
            ->assertSee('Hand in your requirements to renew your scholarship')
            ->assertSee('Certificate of registration')
            ->set("uploads.{$this->gradesRequirement->id}", UploadedFile::fake()->create('grades.pdf', 50, 'application/pdf'))
            ->call('submitRequirement', $this->gradesRequirement->id)
            ->assertHasNoErrors()
            ->assertSee('Your year level this term')
            ->call('saveYearLevel')
            ->assertHasErrors('yearLevel')
            ->set('yearLevel', '3')
            ->call('saveYearLevel')
            ->assertHasNoErrors();

        $this->assertSame(3, $renewal->fresh()->year_level);

        // Submitted once — it can't be changed afterwards.
        Livewire::test('public::track-dashboard', ['offering' => $this->target])
            ->assertDontSee('Submit year level')
            ->set('yearLevel', '4')
            ->call('saveYearLevel')
            ->assertHasErrors('yearLevel');

        $this->assertSame(3, $renewal->fresh()->year_level);

        Livewire::actingAs($this->admin)
            ->test('staff::renewals.index', ['offering' => $this->target])
            ->assertSee('Dela Cruz, Juan')
            ->assertSee(route('staff.renewals.review', $renewal));

        $page = Livewire::actingAs($this->admin)
            ->test('staff::renewals.review', ['application' => $renewal])
            ->call('renew')
            ->assertDispatched('notice', title: 'Couldn\'t renew');

        $submission = $renewal->additionalRequirementSubmissions()->sole();
        $page->call('verifyRequirement', $submission->id)
            ->call('togglePhysicalRequirement', $this->enrolmentRequirement->id)
            ->call('renew')
            ->assertDispatched('notice', title: 'Scholarship renewed');

        $this->assertSame(ApplicationStatus::Awarded, $renewal->fresh()->status);
        $this->assertNotNull($renewal->fresh()->award);
        $this->assertSame(0, app(AwardService::class)->usedSlots($this->target));

        Livewire::test('public::track-dashboard', ['offering' => $this->target])
            ->assertSee('Your scholarship has been renewed');

        Livewire::actingAs($this->admin)->test('staff::grantees.index')->assertSee('Renewed');
    }

    public function test_deny_needs_a_reason(): void
    {
        app(RenewalService::class)->openRenewals($this->target, $this->admin);
        $renewal = $this->target->applications()->sole();

        Livewire::actingAs($this->admin)
            ->test('staff::renewals.review', ['application' => $renewal])
            ->call('deny')
            ->assertHasErrors('decisionRemarks')
            ->set('decisionRemarks', 'Graduated')
            ->call('deny')
            ->assertHasNoErrors();

        $this->assertSame(ApplicationStatus::Denied, $renewal->fresh()->status);
    }

    public function test_renewal_offering_takes_no_applications(): void
    {
        $this->assertFalse(ProgramOffering::open()->whereKey($this->target->id)->exists());

        $this->get(route('programs.apply', $this->target))->assertNotFound();
    }

    public function test_a_renewal_is_reviewed_on_its_own_renewal_review_page(): void
    {
        app(RenewalService::class)->openRenewals($this->target, $this->admin);
        $renewal = $this->target->applications()->sole();

        $this->actingAs($this->admin)
            ->get(route('staff.applications.review', $renewal))
            ->assertRedirect(route('staff.renewals.review', $renewal));

        $this->get(route('staff.renewals.review', $renewal))
            ->assertOk()
            ->assertSee('Dela Cruz, Juan')
            ->assertSee('Renew scholarship');
    }

    public function test_admin_deletes_an_empty_renewal_offering_from_the_tab_menu(): void
    {
        Livewire::actingAs($this->admin)
            ->test('staff::renewals.index', ['offering' => $this->target])
            ->assertSee('Delete offering')
            ->call('deleteRenewalOffering', $this->target->id)
            ->assertRedirect(route('staff.applications.workspace', $this->source));

        $this->assertModelMissing($this->target);
    }

    public function test_a_renewal_offering_holding_renewals_cannot_be_deleted(): void
    {
        app(RenewalService::class)->openRenewals($this->target, $this->admin);

        Livewire::actingAs($this->admin)
            ->test('staff::renewals.index', ['offering' => $this->target])
            ->call('deleteRenewalOffering', $this->target->id)
            ->assertDispatched('notice', title: 'Offering not deleted');

        $this->assertModelExists($this->target);
    }

    public function test_staff_see_no_offering_menu(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($staff)
            ->test('staff::renewals.index', ['offering' => $this->target])
            ->assertDontSee('Delete offering')
            ->call('deleteRenewalOffering', $this->target->id)
            ->assertForbidden();

        $this->assertModelExists($this->target);
    }

    public function test_offerings_list_groups_renewal_offerings_under_the_first(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $this->program])
            ->assertSee('1 renewal')
            ->assertDontSee('Batch 1 renewal');

        $chain = app(RenewalService::class)->chainOf($this->target);
        $this->assertSame([$this->source->id, $this->target->id], $chain->pluck('id')->all());

        Livewire::actingAs($this->admin)
            ->test('admin::program-configuration.offering-view', ['offering' => $this->source])
            ->assertSee('Batch 1 renewal')
            ->assertSee('Add renewal offering');
    }

    public function test_add_renewal_offering_links_it_to_the_source(): void
    {
        Livewire::actingAs($this->admin)
            ->withQueryParams(['renews' => $this->target->id])
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $this->program])
            ->assertSee('Renews scholars awarded in Batch 1 renewal')
            ->set('name', 'Batch 1 second renewal')
            ->set('academic_term_id', (string) $this->secondTerm->id)
            ->set('opens_at', now()->format('Y-m-d\TH:i'))
            ->set('closes_at', now()->addMonth()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $created = ProgramOffering::where('name', 'Batch 1 second renewal')->sole();
        $this->assertSame($this->target->id, $created->renews_offering_id);
        $this->assertSame(0, $created->total_slots);
        $this->assertSame(3, app(RenewalService::class)->chainOf($this->source)->count());
    }

    public function test_renewal_offering_configures_only_renewal_requirements(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $this->target])
            ->assertSee('Renewal requirements')
            ->assertDontSee('Application form')
            ->call('addRequirement')
            ->assertSet('stage', 'renewal')
            ->set('label', 'Good moral certificate')
            ->set('submission_mode', 'physical')
            ->call('saveRequirement')
            ->assertHasNoErrors();

        $this->assertSame(3, $this->target->additionalRequirements()->where('stage', 'renewal')->count());
    }

    public function test_renewal_cannot_be_renewed_until_the_scholar_enters_a_year_level(): void
    {
        app(RenewalService::class)->openRenewals($this->target, $this->admin);
        $renewal = $this->target->applications()->sole();
        $this->assertNull($renewal->year_level);

        $renewal->additionalRequirementSubmissions()->create([
            'offering_additional_requirement_id' => $this->gradesRequirement->id, 'status' => 'verified',
            'file_path' => 'x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf',
        ]);
        $renewal->additionalRequirementSubmissions()->create([
            'offering_additional_requirement_id' => $this->enrolmentRequirement->id, 'physical_received_at' => now(),
        ]);

        $this->expectExceptionMessage('This scholar has not entered their year level yet.');
        app(AwardService::class)->create($renewal->fresh(), $this->admin);
    }

    public function test_scholars_in_4th_year_second_semester_suggest_new_applicant_slots(): void
    {
        app(RenewalService::class)->openRenewals($this->target, $this->admin);
        $renewal = $this->target->applications()->sole();
        $renewal->update(['year_level' => 4, 'status' => 'awarded']);
        Award::create([
            'application_id' => $renewal->id, 'applicant_id' => $renewal->applicant_id,
            'program_offering_id' => $this->target->id, 'academic_term_id' => $this->secondTerm->id,
            'amount' => 5000, 'awarded_by' => $this->admin->id, 'awarded_at' => now(),
        ]);

        // Batch 1 is a first-semester offering with a 2nd-year scholar; the renewal
        // is second semester with the same scholar now in 4th year.
        $this->assertSame(0, app(RenewalService::class)->graduatingCount($this->source));
        $this->assertSame(1, app(RenewalService::class)->graduatingCount($this->target));

        Livewire::actingAs($this->admin)
            ->withQueryParams(['renews' => $this->target->id])
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $this->program])
            ->set('accepts_new_applicants', true)
            ->assertSet('total_slots', '1')
            ->assertSee('Suggested: 1 scholar from Batch 1 renewal in their last term.');
    }

    public function test_renewal_offering_open_to_new_applicants_takes_applications(): void
    {
        $this->target->update(['accepts_new_applicants' => true, 'total_slots' => 2]);

        $this->assertTrue(ProgramOffering::open()->whereKey($this->target->id)->exists());
        $this->get(route('programs.apply', $this->target))->assertOk();

        Livewire::actingAs($this->admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $this->target])
            ->assertSee('Application form')
            ->assertSee('Renewal requirements');

        Livewire::actingAs($this->admin)
            ->test('staff::renewals.index', ['offering' => $this->target])
            ->assertSeeInOrder(['New applications', 'Renewals', 'To review']);

        $this->actingAs($this->admin)
            ->get(route('staff.applications.workspace', $this->target))
            ->assertOk()
            ->assertSee('New applications');
    }

    public function test_applications_list_shows_one_row_per_group(): void
    {
        Livewire::actingAs($this->admin)
            ->test('staff::applications.index')
            ->assertSee('Applications')
            ->assertSee('1 renewal')
            ->assertSee(route('staff.renewals.index', $this->target));
    }
}
