<?php

namespace Tests\Feature\Admin;

use App\Enums\FundingSource;
use App\Enums\OfferingStatus;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\OfferingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramOfferingsTest extends TestCase
{
    use RefreshDatabase;

    private function scholarshipProgram(): ScholarshipProgram
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);

        return ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ]);
    }

    private function academicTerm(): AcademicTerm
    {
        return AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
    }

    public function test_non_admin_cannot_access_offerings_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $program = $this->scholarshipProgram();

        $this->actingAs($staff)->get("/admin/scholarship-programs/{$program->id}/offerings")->assertForbidden();
    }

    public function test_admin_can_create_an_open_offering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('program_offerings', [
            'scholarship_program_id' => $program->id,
            'name' => 'Batch 1',
            'total_slots' => 20,
            'college_scope' => 'all',
        ]);
    }

    public function test_max_applications_cap_closes_registration_early(): void
    {
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => Campus::first()->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(30), 'total_slots' => 20,
            'max_applications' => 1, 'college_scope' => 'all', 'allocation_mode' => 'open',
            'category' => ScholarshipCategory::Academic,
        ]);

        $this->assertSame(OfferingStatus::Open, $offering->registrationStatus());

        $offering->applications()->create([
            'access_token' => str_repeat('a', 64),
            'applicant_id' => Applicant::create([
                'student_id' => 'SID-CAP', 'last_name' => 'Reyes', 'first_name' => 'Ana', 'sex' => 'female', 'email' => 'ana@example.com',
            ])->id,
            'college_id' => College::create(['code' => 'CICS', 'name' => 'College of ICS'])->id,
            'year_level' => 2, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $this->assertSame(OfferingStatus::Closed, $offering->fresh()->registrationStatus());
    }

    public function test_offering_cannot_be_created_without_a_campus_configured(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->call('save');

        $this->assertDatabaseCount('program_offerings', 0);
    }

    public function test_quota_allocation_requires_slots_per_college(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->set('allocation_mode', 'quota')
            ->set('offeringColleges', [(string) $college->id])
            ->call('save')
            ->assertHasErrors(['collegeSlots.'.$college->id]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->set('allocation_mode', 'quota')
            ->set('offeringColleges', [(string) $college->id])
            ->set('collegeSlots.'.$college->id, '10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_colleges', ['college_id' => $college->id, 'slots' => 10]);
    }

    public function test_restricted_scope_requires_at_least_one_eligible_college(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        $component = Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->set('college_scope', 'restricted')
            ->call('save')
            ->assertHasErrors(['offeringColleges']);

        // Open allocation takes the colleges without any slot numbers.
        $component
            ->set('offeringColleges', [(string) $college->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_colleges', ['college_id' => $college->id, 'slots' => null]);
    }

    public function test_an_offering_requires_a_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertDatabaseCount('program_offerings', 0);
    }

    public function test_creating_an_offering_redirects_to_configure_requirements(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->call('save')
            ->assertRedirect(route('admin.offerings.configure', ProgramOffering::where('name', 'Batch 1')->firstOrFail()));
    }

    public function test_admin_can_edit_an_existing_offering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $campus = Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'name' => 'Batch 1',
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('edit', $offering->id)
            ->set('name', 'Batch 2')
            ->set('total_slots', '35')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('program_offerings', ['id' => $offering->id, 'name' => 'Batch 2', 'total_slots' => 35]);
    }

    public function test_a_new_offering_starts_with_no_form_fields_or_eligibility_rules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offerings', ['scholarshipProgram' => $program])
            ->call('create')
            ->set('academic_term_id', (string) $term->id)
            ->set('name', 'Batch 1')
            ->set('category', ScholarshipCategory::Academic->value)
            ->set('opens_at', '2026-08-10T08:00')
            ->set('closes_at', '2026-09-10T17:00')
            ->set('total_slots', '20')
            ->set('allocation_mode', 'quota')
            ->set('offeringColleges', [(string) $college->id])
            ->set('collegeSlots.'.$college->id, '10')
            ->call('save')
            ->assertHasNoErrors();

        $offering = ProgramOffering::where('scholarship_program_id', $program->id)->firstOrFail();

        $this->assertSame('quota', $offering->allocation_mode->value);
        $this->assertSame(0, $offering->formFields()->count());
        $this->assertSame(0, $offering->eligibilityRules()->count());
        $this->assertSame(10, $offering->offeringColleges()->firstOrFail()->slots);
    }

    public function test_editing_one_offerings_requirements_does_not_change_a_sibling_offering(): void
    {
        Campus::create(['name' => 'Aparri']);
        $program = $this->scholarshipProgram();
        $term = $this->academicTerm();

        $offering = app(OfferingService::class)->openOffering($program, [
            'academic_term_id' => $term->id,
            'campus_id' => Campus::first()->id,
            'opens_at' => '2026-08-10 08:00',
            'closes_at' => '2026-09-10 17:00',
            'total_slots' => 20,
            'category' => ScholarshipCategory::Academic,
        ]);
        $sibling = app(OfferingService::class)->openOffering($program, [
            'academic_term_id' => $term->id,
            'campus_id' => Campus::first()->id,
            'opens_at' => '2026-08-10 08:00',
            'closes_at' => '2026-09-10 17:00',
            'total_slots' => 20,
            'category' => ScholarshipCategory::Academic,
        ]);

        $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);

        $this->assertSame(1, $offering->formFields()->count());
        $this->assertSame(0, $sibling->formFields()->count());
    }
}
