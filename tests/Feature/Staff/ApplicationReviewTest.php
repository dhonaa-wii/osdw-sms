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
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AwardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    private function applicationForReview(string $status = 'screening'): Application
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
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20, 'amount' => 5000,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);
        $field = $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);

        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $program = Program::create([
            'college_id' => $college->id, 'code' => 'BSIT', 'name' => 'BS Information Technology',
            'degree_level' => DegreeLevel::Undergraduate,
        ]);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $application = $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => $status, 'submitted_at' => now(),
        ]);
        $application->documents()->create([
            'offering_form_field_id' => $field->id, 'file_path' => 'applications/test/cor.pdf',
            'original_name' => 'cor.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000, 'status' => 'pending',
        ]);

        return $application->load('programOffering');
    }

    public function test_guest_cannot_access_the_review_page(): void
    {
        $application = $this->applicationForReview();

        $this->get(route('staff.applications.review', $application))->assertRedirect(route('login'));
    }

    public function test_admin_can_access_staff_review(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $application = $this->applicationForReview();

        $this->actingAs($admin)->get(route('staff.applications.review', $application))->assertOk();
    }

    public function test_staff_can_verify_a_document(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();
        $document = $application->documents->first();

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('verifyDocument', $document->id);

        $this->assertDatabaseHas('application_documents', ['id' => $document->id, 'status' => 'verified', 'verified_by' => $staff->id]);
    }

    public function test_endorsing_an_application_shortlists_it(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();
        $document = $application->documents->first();

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('verifyDocument', $document->id)
            ->set('decisionRemarks', 'Meets all criteria.')
            ->call('endorse');

        $application->refresh();
        $this->assertSame('shortlisted', $application->status->value);
    }

    public function test_endorsing_is_blocked_while_documents_are_unverified(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->set('decisionRemarks', 'Meets all criteria.')
            ->call('endorse')
            ->assertDispatched('notice', title: 'Documents not verified');

        $this->assertSame('screening', $application->fresh()->status->value);
    }

    public function test_endorsing_is_blocked_when_a_required_physical_copy_is_not_received(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();
        $document = $application->documents->first();
        $document->offeringFormField()->update(['requires_physical_copy' => true]);

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('verifyDocument', $document->id)
            ->set('decisionRemarks', 'Meets all criteria.')
            ->call('endorse')
            ->assertDispatched('notice', title: 'Documents not verified');

        $this->assertSame('screening', $application->fresh()->status->value);
    }

    public function test_physical_copy_checkbox_shows_only_for_documents_that_require_one(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->assertDontSee('Physical copy received');

        $application->documents->first()->offeringFormField()->update(['requires_physical_copy' => true]);

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application->fresh()])
            ->assertSee('Physical copy received');
    }

    public function test_physical_copy_cannot_be_ticked_on_a_document_that_does_not_require_one(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();
        $document = $application->documents->first();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('togglePhysicalReceived', $document->id);
    }

    public function test_awarding_records_the_amount_immediately(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview(status: 'shortlisted');
        $application->documents->first()->update(['status' => 'verified', 'verified_by' => $staff->id, 'verified_at' => now()]);

        $award = app(AwardService::class)->create($application->fresh(), $staff);

        $this->assertNotNull($award->awarded_at);
        $this->assertSame('awarded', $application->fresh()->status->value);
    }

    public function test_staff_can_reject_an_application_without_remarks(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame('denied', $application->fresh()->status->value);
        $this->assertNull($application->statusLogs()->latest('id')->first()->reason);
    }

    public function test_staff_can_view_an_uploaded_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('applications/test/cor.pdf', 'PDF content');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();
        $document = $application->documents->first();

        $this->actingAs($staff)
            ->get(route('staff.applications.documents.show', $document))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_guest_cannot_view_an_uploaded_document(): void
    {
        $application = $this->applicationForReview();
        $document = $application->documents->first();

        $this->get(route('staff.applications.documents.show', $document))->assertRedirect(route('login'));
    }

    public function test_workspace_shows_slot_counters_and_queue(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $application = $this->applicationForReview();

        $this->actingAs($staff)
            ->get(route('staff.applications.workspace', $application->programOffering))
            ->assertOk()
            ->assertSee('0 / 20')
            ->assertSee($application->applicant->student_id);
    }

    /**
     * @return array{0: ProgramOffering, 1: College, 2: Program}
     */
    private function offeringWithCollegeAndProgram(): array
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
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20, 'amount' => 5000,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $program = Program::create([
            'college_id' => $college->id, 'code' => 'P'.Str::random(3), 'name' => 'BS Information Technology',
            'degree_level' => DegreeLevel::Undergraduate,
        ]);

        return [$offering, $college, $program];
    }

    private function applicationFor(ProgramOffering $offering, College $college, Program $program, string $status): Application
    {
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);

        return $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => $status, 'submitted_at' => now(),
        ]);
    }

    public function test_queue_excludes_applicants_already_sent_to_shortlist(): void
    {
        [$offering, $college, $program] = $this->offeringWithCollegeAndProgram();
        $stillScreening = $this->applicationFor($offering, $college, $program, 'screening');
        $shortlisted = $this->applicationFor($offering, $college, $program, 'shortlisted');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $rows = Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->viewData('queue');

        $this->assertTrue($rows->contains('id', $stillScreening->id));
        $this->assertFalse($rows->contains('id', $shortlisted->id));
    }

    public function test_queue_excludes_rejected_applicants(): void
    {
        [$offering, $college, $program] = $this->offeringWithCollegeAndProgram();
        $stillScreening = $this->applicationFor($offering, $college, $program, 'screening');
        $denied = $this->applicationFor($offering, $college, $program, 'denied');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $rows = Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->viewData('queue');

        $this->assertTrue($rows->contains('id', $stillScreening->id));
        $this->assertFalse($rows->contains('id', $denied->id));
    }

    public function test_queue_search_filters_by_student_id(): void
    {
        [$offering, $college, $program] = $this->offeringWithCollegeAndProgram();
        $match = $this->applicationFor($offering, $college, $program, 'screening');
        $other = $this->applicationFor($offering, $college, $program, 'screening');

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $rows = Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->set('search', $match->applicant->student_id)
            ->viewData('queue');

        $this->assertTrue($rows->contains('id', $match->id));
        $this->assertFalse($rows->contains('id', $other->id));
    }
}
