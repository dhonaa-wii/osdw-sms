<?php

namespace Tests\Feature\Admin;

use App\Enums\FundingSource;
use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScholarshipProgramsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_scholarship_programs_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/scholarship-programs')->assertForbidden();
    }

    public function test_admin_can_create_a_scholarship_program(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('create')
            ->set('name', 'DOST-SEI Undergraduate Academic Fellowship')
            ->set('provider_id', (string) $provider->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scholarship_programs', [
            'name' => 'DOST-SEI Undergraduate Academic Fellowship',
            'provider_id' => $provider->id,
        ]);
    }

    public function test_provider_must_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('create')
            ->set('name', 'DOST-SEI Undergraduate Academic Fellowship')
            ->set('provider_id', '999999')
            ->call('save')
            ->assertHasErrors(['provider_id' => 'exists']);
    }

    public function test_admin_can_edit_a_scholarship_program(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create([
            'name' => 'Original name', 'provider_id' => $provider->id,
        ]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('edit', $program->id)
            ->set('name', 'Renamed program')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scholarship_programs', ['id' => $program->id, 'name' => 'Renamed program']);
    }

    public function test_admin_can_toggle_scholarship_program_active_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create([
            'name' => 'Program', 'provider_id' => $provider->id, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('toggleActive', $program->id);

        $this->assertDatabaseHas('scholarship_programs', ['id' => $program->id, 'is_active' => false]);
    }

    public function test_admin_can_add_a_new_provider_inline_and_it_is_selected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('create')
            ->set('name', 'DOST-SEI Undergraduate Academic Fellowship')
            ->call('openProviderModal')
            ->set('newProviderName', 'New Provider Inc.')
            ->set('newProviderFundingSource', FundingSource::LocalNonGovernment->value)
            ->set('newProviderAllocatedBudget', '50000')
            ->call('saveNewProvider')
            ->assertHasNoErrors()
            ->assertSet('showProviderModal', false)
            ->call('save')
            ->assertHasNoErrors();

        $provider = Provider::where('name', 'New Provider Inc.')->firstOrFail();
        $this->assertSame(50000.0, (float) $provider->allocated_budget);

        $this->assertDatabaseHas('scholarship_programs', [
            'name' => 'DOST-SEI Undergraduate Academic Fellowship',
            'provider_id' => $provider->id,
        ]);
    }

    public function test_new_provider_requires_a_name_and_funding_source(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('create')
            ->call('openProviderModal')
            ->call('saveNewProvider')
            ->assertHasErrors(['newProviderName' => 'required', 'newProviderFundingSource' => 'required']);
    }

    public function test_failed_new_provider_validation_does_not_lose_outer_form_input(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $component = Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->call('create')
            ->set('name', 'DOST-SEI Undergraduate Academic Fellowship')
            ->call('openProviderModal')
            ->call('saveNewProvider')
            ->assertHasErrors(['newProviderName']);

        $component->assertSet('name', 'DOST-SEI Undergraduate Academic Fellowship');
    }
}
