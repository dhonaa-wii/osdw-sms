<?php

namespace Tests\Feature\Staff;

use App\Enums\DegreeLevel;
use App\Enums\FundingSource;
use App\Enums\RequirementSubmission;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
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
use App\Services\AdditionalRequirementService;
use App\Services\AwardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdditionalRequirementTest extends TestCase
{
    use RefreshDatabase;

    private function programOffering(): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $term = AcademicTerm::firstOrCreate(
            ['school_year' => '2026-2027', 'semester' => Semester::First],
            ['start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true],
        );
        $campus = Campus::firstOrCreate(['name' => 'Aparri']);

        return ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'amount' => 5000,
        ]);
    }

    private function shortlisted(ProgramOffering $offering): Application
    {
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'College of ICS']);
        Program::firstOrCreate(
            ['code' => 'BSIT', 'college_id' => $college->id],
            ['name' => 'BS Information Technology', 'degree_level' => DegreeLevel::Undergraduate]
        );
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Aquino', 'first_name' => 'Noel',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);

        return $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'shortlisted', 'submitted_at' => now()->subDays(3),
        ]);
    }

    private function requirement(ProgramOffering $offering, RequirementSubmission $mode = RequirementSubmission::PhysicalCopy): OfferingAdditionalRequirement
    {
        return $offering->additionalRequirements()->create([
            'label' => 'Original transcript', 'submission_mode' => $mode,
        ]);
    }

    public function test_requirements_are_set_once_and_apply_to_every_shortlisted_applicant(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $first = $this->shortlisted($offering);
        $second = $this->shortlisted($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('openRequirements')
            ->set('requirementLines', [
                ['label' => 'Signed parent consent form', 'submission_mode' => 'upload'],
                ['label' => '', 'submission_mode' => 'upload'],
                ['label' => 'Affidavit of indigency', 'submission_mode' => 'physical_copy'],
            ])
            ->call('saveRequirements')
            ->assertHasNoErrors();

        $this->assertSame(2, $offering->additionalRequirements()->count());
        $this->assertSame(2, $first->outstandingAdditionalRequirements()->count());
        $this->assertSame(2, $second->outstandingAdditionalRequirements()->count());
        $this->assertDatabaseHas('offering_additional_requirements', [
            'program_offering_id' => $offering->id,
            'label' => 'Affidavit of indigency',
            'submission_mode' => 'physical_copy',
        ]);
    }

    public function test_saving_with_no_named_requirement_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('openRequirements')
            ->set('requirementLines', [['label' => '   ', 'submission_mode' => 'upload']])
            ->call('saveRequirements')
            ->assertHasErrors(['requirementLines']);

        $this->assertSame(0, $offering->additionalRequirements()->count());
    }

    public function test_a_duplicate_label_is_not_added_twice(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $this->requirement($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('openRequirements')
            ->set('requirementLines', [['label' => 'Original transcript', 'submission_mode' => 'physical_copy']])
            ->call('saveRequirements')
            ->assertHasNoErrors();

        $this->assertSame(1, $offering->additionalRequirements()->count());
    }

    public function test_awarding_is_blocked_while_a_requirement_is_outstanding(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('startAward', $application->id)
            ->call('confirmAward')
            ->assertHasErrors(['slots']);

        $this->assertDatabaseMissing('awards', ['application_id' => $application->id]);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $application->id, $requirement->id)
            ->call('startAward', $application->id)
            ->call('confirmAward')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('awards', ['application_id' => $application->id]);
    }

    public function test_staff_cannot_tick_an_upload_requirement_the_applicant_has_not_sent(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering, RequirementSubmission::Upload);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $application->id, $requirement->id);

        $this->assertDatabaseCount('application_additional_requirements', 0);
        $this->assertSame(1, $application->fresh()->outstandingAdditionalRequirements()->count());
    }

    public function test_staff_can_untick_an_upload_the_applicant_sent(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering, RequirementSubmission::Upload);

        app(AdditionalRequirementService::class)->submitUpload(
            $application,
            $requirement,
            UploadedFile::fake()->create('consent.pdf', 120, 'application/pdf'),
        );

        $path = $application->additionalRequirementReceipts()->first()->file_path;

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $application->id, $requirement->id);

        $this->assertDatabaseCount('application_additional_requirements', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_the_award_service_refuses_an_applicant_with_an_outstanding_requirement(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $this->requirement($offering);

        $this->expectException(\RuntimeException::class);

        app(AwardService::class)->create($application, $staff);
    }

    public function test_the_review_page_cannot_award_around_an_outstanding_requirement(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $this->requirement($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.review', ['application' => $application])
            ->call('startAward')
            ->call('confirmAward')
            ->assertHasErrors(['slots']);

        $this->assertDatabaseMissing('awards', ['application_id' => $application->id]);
    }

    public function test_ticking_one_applicant_does_not_tick_the_others(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $first = $this->shortlisted($offering);
        $second = $this->shortlisted($offering);
        $requirement = $this->requirement($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $first->id, $requirement->id);

        $this->assertSame(0, $first->fresh()->outstandingAdditionalRequirements()->count());
        $this->assertSame(1, $second->fresh()->outstandingAdditionalRequirements()->count());
    }

    public function test_marking_received_can_be_undone(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $application->id, $requirement->id)
            ->call('toggleRequirementReceived', $application->id, $requirement->id);

        $this->assertDatabaseCount('application_additional_requirements', 0);
    }

    public function test_removing_a_requirement_drops_every_applicants_tick(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $application->id, $requirement->id)
            ->call('removeRequirement', $requirement->id);

        $this->assertDatabaseCount('offering_additional_requirements', 0);
        $this->assertDatabaseCount('application_additional_requirements', 0);
    }

    public function test_staff_cannot_touch_a_requirement_from_another_offering(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $otherOffering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($otherOffering);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('toggleRequirementReceived', $application->id, $requirement->id);
    }

    public function test_shortlisted_applicant_can_upload_a_requirement_from_their_tracking_page(): void
    {
        Storage::fake('local');
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering, RequirementSubmission::Upload);

        $this->withSession(['tracking_applicant_id' => $application->applicant_id]);

        Livewire::test('public::track-dashboard')
            ->set("requirementUploads.{$requirement->id}", UploadedFile::fake()->create('consent.pdf', 120, 'application/pdf'))
            ->call('submitRequirement', $application->id, $requirement->id)
            ->assertHasNoErrors();

        $receipt = $application->additionalRequirementReceipts()->first();
        $this->assertNotNull($receipt);
        $this->assertSame('consent.pdf', $receipt->original_name);
        Storage::disk('local')->assertExists($receipt->file_path);
        $this->assertSame(0, $application->fresh()->outstandingAdditionalRequirements()->count());
    }

    public function test_a_physical_copy_requirement_cannot_be_uploaded(): void
    {
        Storage::fake('local');
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $requirement = $this->requirement($offering, RequirementSubmission::PhysicalCopy);

        $this->withSession(['tracking_applicant_id' => $application->applicant_id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('public::track-dashboard')
            ->set("requirementUploads.{$requirement->id}", UploadedFile::fake()->create('consent.pdf', 120, 'application/pdf'))
            ->call('submitRequirement', $application->id, $requirement->id);
    }

    public function test_the_tracking_page_shows_what_a_shortlisted_applicant_still_owes(): void
    {
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering);
        $this->requirement($offering, RequirementSubmission::PhysicalCopy);

        $this->withSession(['tracking_applicant_id' => $application->applicant_id]);

        Livewire::test('public::track-dashboard')
            ->assertSee('Documents needed')
            ->assertSee('Original transcript')
            ->assertSee('Submit the original at the OSDW office.');
    }
}
