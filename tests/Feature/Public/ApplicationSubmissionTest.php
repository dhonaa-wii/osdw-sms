<?php

namespace Tests\Feature\Public;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function openOffering(array $overrides = [], string $programName = 'DOST-SEI Fellowship', ScholarshipCategory $category = ScholarshipCategory::Academic): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create([
            'name' => $programName, 'provider_id' => $provider->id,
            'category' => $category,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);

        return ProgramOffering::create(array_merge([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open',
        ], $overrides));
    }

    public function test_guest_can_browse_open_offerings_only(): void
    {
        $open = $this->openOffering(programName: 'Open Grant');
        $upcoming = $this->openOffering(['opens_at' => now()->addMonth(), 'closes_at' => now()->addMonths(2)], programName: 'Upcoming Grant');

        $this->get('/programs')
            ->assertOk()
            ->assertSee($open->scholarshipProgram->name)
            ->assertDontSee($upcoming->scholarshipProgram->name);
    }

    public function test_guest_can_view_offering_detail_with_eligibility_and_requirements(): void
    {
        $offering = $this->openOffering();
        $offering->eligibilityRules()->create([
            'field' => 'gwa', 'operator' => 'gte', 'value' => '80', 'message' => 'GWA must be 80 or better.',
        ]);
        $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);

        $this->get("/programs/{$offering->id}")
            ->assertOk()
            ->assertSee('GWA must be')
            ->assertSee('Certificate of Registration');
    }

    public function test_detail_page_404s_for_a_non_open_offering(): void
    {
        $offering = $this->openOffering(['opens_at' => now()->addMonth(), 'closes_at' => now()->addMonths(2)]);

        $this->get("/programs/{$offering->id}")->assertNotFound();
    }

    public function test_student_can_submit_a_full_application(): void
    {
        Storage::fake('local');

        $offering = $this->openOffering();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        $textField = $offering->formFields()->create([
            'field_type' => 'text', 'label' => "Guardian's occupation", 'is_required' => false, 'sort_order' => 1,
        ]);
        $fileField = $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 2,
        ]);

        Livewire::test('public::apply', ['offering' => $offering])
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('first_name', 'Juan')
            ->set('sex', 'male')
            ->set('email', 'juan@example.com')
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->set('gwa', '92.00')
            ->set("responses.{$textField->id}", 'Farmer')
            ->set("uploads.{$fileField->id}", UploadedFile::fake()->create('cor.pdf', 500, 'application/pdf'))
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applicants', ['student_id' => '2023-00123', 'email' => 'juan@example.com']);

        $application = Application::firstOrFail();
        $this->assertMatchesRegularExpression('/^SCH-\d{4}-[A-Z0-9]{6}$/', $application->reference_no);
        $this->assertSame($college->id, $application->college_id);
        $this->assertSame('screening', $application->status->value);

        $this->assertDatabaseHas('application_field_responses', [
            'application_id' => $application->id,
            'offering_form_field_id' => $textField->id,
            'value' => 'Farmer',
        ]);
        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'offering_form_field_id' => $fileField->id,
            'original_name' => 'cor.pdf',
        ]);
    }

    public function test_required_file_field_is_validated(): void
    {
        $offering = $this->openOffering();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $fileField = $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);

        Livewire::test('public::apply', ['offering' => $offering])
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('first_name', 'Juan')
            ->set('sex', 'male')
            ->set('email', 'juan@example.com')
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->call('submit')
            ->assertHasErrors(["uploads.{$fileField->id}"]);
    }

    public function test_duplicate_application_to_same_offering_is_rejected(): void
    {
        $offering = $this->openOffering();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => '2023-00123', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $applicant->applications()->create([
            'reference_no' => 'SCH-2026-AAAAAA', 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        Livewire::test('public::apply', ['offering' => $offering])
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('first_name', 'Juan')
            ->set('sex', 'male')
            ->set('email', 'juan@example.com')
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->call('submit')
            ->assertHasErrors(['student_id']);

        $this->assertSame(1, Application::count());
    }

    public function test_existing_applicant_data_prefills_and_other_applications_are_listed(): void
    {
        $offering = $this->openOffering();
        $otherOffering = $this->openOffering();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => '2023-00123', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $applicant->applications()->create([
            'reference_no' => 'SCH-2026-AAAAAA', 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $otherOffering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        Livewire::test('public::apply', ['offering' => $offering])
            ->set('student_id', '2023-00123')
            ->assertSet('last_name', 'Dela Cruz')
            ->assertSet('email', 'juan@example.com')
            ->assertSee('SCH-2026-AAAAAA');
    }

    public function test_college_choices_are_restricted_when_offering_scope_is_restricted(): void
    {
        $offering = $this->openOffering(['college_scope' => 'restricted']);
        $eligibleCollege = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $otherCollege = College::create(['code' => 'COE', 'name' => 'College of Engineering']);
        $offering->offeringColleges()->create(['college_id' => $eligibleCollege->id]);

        Livewire::test('public::apply', ['offering' => $offering])
            ->assertSee($eligibleCollege->name)
            ->assertDontSee($otherCollege->name);
    }

    public function test_gwa_is_not_required_when_program_is_non_academic(): void
    {
        $offering = $this->openOffering(category: ScholarshipCategory::NonAcademic);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::test('public::apply', ['offering' => $offering])
            ->assertDontSee('GWA')
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('first_name', 'Juan')
            ->set('sex', 'male')
            ->set('email', 'juan@example.com')
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applications', ['college_id' => $college->id, 'gwa' => null]);
    }

    public function test_gwa_is_required_when_program_is_academic(): void
    {
        $offering = $this->openOffering(category: ScholarshipCategory::Academic);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::test('public::apply', ['offering' => $offering])
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('first_name', 'Juan')
            ->set('sex', 'male')
            ->set('email', 'juan@example.com')
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->call('submit')
            ->assertHasErrors(['gwa']);
    }
}
