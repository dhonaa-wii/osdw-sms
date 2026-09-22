<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollegesTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_colleges_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/colleges')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/colleges')->assertRedirect('/login');
    }

    public function test_admin_can_view_colleges_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        College::create(['code' => 'CICS', 'name' => 'College of Information and Computing Sciences']);

        $this->actingAs($admin)
            ->get('/admin/colleges')
            ->assertOk()
            ->assertSee('CICS')
            ->assertSee('College of Information and Computing Sciences');
    }

    public function test_admin_can_create_a_college(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.colleges')
            ->call('create')
            ->set('code', 'CFAS')
            ->set('name', 'College of Fisheries and Aquatic Sciences')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('colleges', [
            'code' => 'CFAS',
            'name' => 'College of Fisheries and Aquatic Sciences',
            'is_active' => true,
        ]);
    }

    public function test_code_is_required_and_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::actingAs($admin)
            ->test('admin::master-data.colleges')
            ->call('create')
            ->set('code', '')
            ->set('name', 'Some College')
            ->call('save')
            ->assertHasErrors(['code' => 'required']);

        Livewire::actingAs($admin)
            ->test('admin::master-data.colleges')
            ->call('create')
            ->set('code', 'CICS')
            ->set('name', 'Duplicate Code College')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_admin_can_edit_a_college_without_tripping_its_own_unique_code(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::actingAs($admin)
            ->test('admin::master-data.colleges')
            ->call('edit', $college->id)
            ->assertSet('code', 'CICS')
            ->set('name', 'College of Information and Computing Sciences')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('colleges', [
            'id' => $college->id,
            'code' => 'CICS',
            'name' => 'College of Information and Computing Sciences',
        ]);
    }

    public function test_admin_can_toggle_college_active_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.colleges')
            ->call('toggleActive', $college->id);

        $this->assertDatabaseHas('colleges', ['id' => $college->id, 'is_active' => false]);
    }
}
