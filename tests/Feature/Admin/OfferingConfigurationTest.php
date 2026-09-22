<?php

namespace Tests\Feature\Admin;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Campus;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfferingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function programOffering(): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);

        return ProgramOffering::create([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open',
        ]);
    }

    public function test_non_admin_cannot_access_configuration_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();

        $this->actingAs($staff)->get("/admin/offerings/{$offering->id}/configure")->assertForbidden();
    }

    public function test_admin_can_add_a_file_field(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'Certificate of Registration')
            ->set('fields.0.field_type', 'file')
            ->set('required_gwa', '85')
            ->call('saveFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_form_fields', [
            'program_offering_id' => $offering->id,
            'label' => 'Certificate of Registration',
            'field_type' => 'file',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_add_a_text_field(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', "Guardian's occupation")
            ->set('fields.0.field_type', 'text')
            ->set('required_gwa', '85')
            ->call('saveFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_form_fields', [
            'program_offering_id' => $offering->id,
            'label' => "Guardian's occupation",
            'field_type' => 'text',
            'accepted_formats' => null,
            'max_size_mb' => null,
        ]);
    }

    public function test_select_field_requires_choices(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'Household income bracket')
            ->set('fields.0.field_type', 'select')
            ->set('fields.0.options', '')
            ->call('saveFields')
            ->assertHasErrors(['fields.0.options']);
    }

    public function test_admin_can_reorder_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        $component = Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'First field')
            ->call('addField')
            ->set('fields.1.label', 'Second field')
            ->call('moveFieldDown', 0)
            ->set('required_gwa', '85')
            ->call('saveFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_form_fields', ['label' => 'Second field', 'sort_order' => 1]);
        $this->assertDatabaseHas('offering_form_fields', ['label' => 'First field', 'sort_order' => 2]);
    }

    public function test_all_eight_field_types_save_correctly_in_one_offering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        $component = Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering]);

        $types = ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio', 'file'];

        foreach ($types as $i => $type) {
            $component->call('addField')
                ->set("fields.{$i}.label", ucfirst($type).' field')
                ->set("fields.{$i}.field_type", $type);

            if (in_array($type, ['select', 'radio'], true)) {
                $component->set("fields.{$i}.options", "Choice A\nChoice B");
            }
        }

        $component->set('required_gwa', '85')->call('saveFields')->assertHasNoErrors();

        foreach ($types as $type) {
            $this->assertDatabaseHas('offering_form_fields', [
                'program_offering_id' => $offering->id,
                'label' => ucfirst($type).' field',
                'field_type' => $type,
                'options' => in_array($type, ['select', 'radio'], true) ? "Choice A\nChoice B" : null,
                'accepted_formats' => $type === 'file' ? 'pdf,jpg,png' : null,
                'max_size_mb' => $type === 'file' ? 5 : null,
            ]);
        }

        $this->assertSame(8, $offering->formFields()->count());
    }

    public function test_admin_can_add_an_image_field_with_default_accepted_formats(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', '2x2 ID photo')
            ->set('fields.0.field_type', 'image')
            ->assertSet('fields.0.accepted_formats', 'jpg,jpeg,png')
            ->set('required_gwa', '85')
            ->call('saveFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_form_fields', [
            'program_offering_id' => $offering->id,
            'label' => '2x2 ID photo',
            'field_type' => 'image',
            'accepted_formats' => 'jpg,jpeg,png',
            'max_size_mb' => 5,
        ]);
    }

    public function test_switching_to_select_auto_seeds_placeholder_choices(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'Household income bracket')
            ->set('fields.0.field_type', 'select')
            ->assertSet('fields.0.options', "Option 1\nOption 2\nOption 3");
    }

    public function test_select_field_rejects_blank_only_choices(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'Household income bracket')
            ->set('fields.0.field_type', 'select')
            ->set('fields.0.options', "  \n   \n")
            ->call('saveFields')
            ->assertHasErrors(['fields.0.options']);
    }

    public function test_required_gwa_is_mandatory_for_an_academic_offering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'Certificate of Registration')
            ->set('fields.0.field_type', 'file')
            ->set('required_gwa', '')
            ->call('saveFields')
            ->assertHasErrors(['required_gwa']);
    }

    public function test_saving_required_gwa_creates_a_gwa_eligibility_rule(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->set('required_gwa', '85')
            ->call('saveFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('offering_eligibility_rules', [
            'program_offering_id' => $offering->id,
            'field' => 'gwa',
            'operator' => 'gte',
            'value' => '85',
        ]);
    }

    public function test_removing_a_field_and_saving_deletes_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $offering = $this->programOffering();

        $component = Livewire::actingAs($admin)
            ->test('admin::program-configuration.offering-configuration', ['offering' => $offering])
            ->call('addField')
            ->set('fields.0.label', 'Certificate of Registration')
            ->set('fields.0.field_type', 'file')
            ->set('required_gwa', '85')
            ->call('saveFields');

        $fieldId = $offering->formFields()->firstOrFail()->id;

        $component->call('removeField', 0)->call('saveFields');

        $this->assertDatabaseMissing('offering_form_fields', ['id' => $fieldId]);
    }
}
