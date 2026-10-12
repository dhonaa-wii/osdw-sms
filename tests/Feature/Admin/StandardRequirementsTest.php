<?php

namespace Tests\Feature\Admin;

use App\Enums\ApplicationKind;
use App\Enums\FundingSource;
use App\Enums\RequirementSource;
use App\Enums\RequirementStage;
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
use App\Models\StandardFormField;
use App\Models\StandardRequirement;
use App\Models\User;
use App\Services\StandardRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StandardRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function offering(): ProgramOffering
    {
        $provider = Provider::create(['name' => 'CHED', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create(['name' => 'CHED Merit', 'provider_id' => $provider->id]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);

        return ProgramOffering::create([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::NonAcademic, 'amount' => 5000,
        ]);
    }

    private function standard(): void
    {
        StandardFormField::create(['field_type' => 'text', 'label' => 'Reason for applying', 'is_required' => true, 'sort_order' => 1]);
        StandardRequirement::create(['label' => 'Signed contract', 'submission_mode' => 'physical', 'sort_order' => 1]);
    }

    public function test_only_admin_opens_the_standard_requirements_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::OsdwStaff]))
            ->get('/admin/scholarship-programs/standard-requirements')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get('/admin/scholarship-programs/standard-requirements')->assertOk()->assertSee('Standard requirements');
    }

    public function test_admin_saves_the_standard_form_and_a_disbursement_requirement(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.standard-requirements')
            ->call('addField')
            ->set('fields.0.label', 'Reason for applying')
            ->call('saveFields')
            ->assertHasNoErrors()
            ->call('addRequirement')
            ->set('label', 'Signed contract')
            ->set('submission_mode', 'physical')
            ->call('saveRequirement')
            ->assertHasNoErrors();

        $this->assertSame(['Reason for applying'], StandardFormField::pluck('label')->all());
        $this->assertSame(['Signed contract'], StandardRequirement::pluck('label')->all());
    }

    public function test_an_offering_copies_the_standard_requirements_and_follows_later_changes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->offering();
        $offering->additionalRequirements()->create(['stage' => RequirementStage::Shortlist, 'label' => 'Interview form', 'submission_mode' => 'physical']);
        $this->standard();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('useStandardRequirements')
            ->assertSet('fields.0.label', 'Reason for applying');

        $offering->refresh();
        $this->assertSame(RequirementSource::Standard, $offering->requirements_source);
        $this->assertSame(['Interview form'], $offering->additionalRequirements()->where('stage', 'shortlist')->pluck('label')->all());
        $this->assertSame(['Signed contract'], $offering->additionalRequirements()->where('stage', 'award')->pluck('label')->all());

        StandardRequirement::query()->update(['label' => 'Notarized contract']);
        app(StandardRequirementService::class)->syncOfferings();

        $this->assertSame(['Notarized contract'], $offering->additionalRequirements()->where('stage', 'award')->pluck('label')->all());
    }

    public function test_the_copy_is_frozen_once_applications_come_in(): void
    {
        $offering = $this->offering();
        $this->standard();
        app(StandardRequirementService::class)->useStandard($offering);

        $applicant = Applicant::create([
            'student_id' => '2026-00001', 'last_name' => 'Cruz', 'first_name' => 'Ana', 'sex' => 'female', 'email' => 'ana@example.com',
        ]);
        $applicant->applications()->create([
            'access_token' => str_repeat('a', 64), 'program_offering_id' => $offering->id, 'college_id' => College::create(['name' => 'College of Teacher Education', 'code' => 'CTE'])->id,
            'kind' => ApplicationKind::New, 'year_level' => 1, 'status' => 'screening', 'submitted_at' => now(),
        ]);

        StandardFormField::query()->update(['label' => 'Changed']);
        app(StandardRequirementService::class)->syncOfferings();

        $this->assertSame(['Reason for applying'], $offering->formFields()->pluck('label')->all());
        $this->assertFalse(app(StandardRequirementService::class)->canUseStandard($offering));
    }

    public function test_a_standard_offering_cannot_edit_its_form_until_set_manually(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->offering();
        $this->standard();
        app(StandardRequirementService::class)->useStandard($offering);

        $component = Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->set('fields.0.label', 'Edited here')
            ->call('saveFields')
            ->assertDispatched('notice');

        $this->assertSame(['Reason for applying'], $offering->formFields()->pluck('label')->all());

        $component->call('setRequirementsManually')
            ->set('fields.0.label', 'Edited here')
            ->call('saveFields');

        $this->assertSame(['Edited here'], $offering->formFields()->pluck('label')->all());
        $this->assertSame(RequirementSource::Manual, $offering->refresh()->requirements_source);
    }
}
