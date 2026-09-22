<?php

namespace Tests\Feature\Admin;

use App\Enums\FundingSource;
use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProvidersTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_providers_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/providers')->assertForbidden();
    }

    public function test_admin_can_create_a_provider_with_optional_contact_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.providers')
            ->call('create')
            ->set('name', 'DOST-SEI')
            ->set('funding_source', FundingSource::NationalGovernment->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('providers', [
            'name' => 'DOST-SEI',
            'funding_source' => FundingSource::NationalGovernment->value,
            'contact_person' => null,
            'contact_email' => null,
            'allocated_budget' => null,
        ]);
    }

    public function test_admin_can_set_an_allocated_budget(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.providers')
            ->call('create')
            ->set('name', 'DOST-SEI')
            ->set('funding_source', FundingSource::NationalGovernment->value)
            ->set('allocated_budget', '150000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('providers', [
            'name' => 'DOST-SEI',
            'allocated_budget' => 150000,
        ]);
    }

    public function test_funding_source_is_required(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.providers')
            ->call('create')
            ->set('name', 'DOST-SEI')
            ->set('funding_source', '')
            ->call('save')
            ->assertHasErrors(['funding_source' => 'required']);
    }

    public function test_contact_email_must_be_valid_when_provided(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.providers')
            ->call('create')
            ->set('name', 'DOST-SEI')
            ->set('funding_source', FundingSource::NationalGovernment->value)
            ->set('contact_email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['contact_email' => 'email']);
    }

    public function test_admin_can_toggle_provider_active_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.providers')
            ->call('toggleActive', $provider->id);

        $this->assertDatabaseHas('providers', ['id' => $provider->id, 'is_active' => false]);
    }
}
