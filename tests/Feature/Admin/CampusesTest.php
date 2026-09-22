<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampusesTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_campus_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/campus')->assertForbidden();
    }

    public function test_admin_can_set_the_campus_name_when_none_exists_yet(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.campuses')
            ->set('name', 'Aparri')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campuses', ['name' => 'Aparri']);
        $this->assertSame(1, Campus::count());
    }

    public function test_saving_again_updates_the_same_campus_instead_of_creating_a_second_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $campus = Campus::create(['name' => 'Aparri']);

        Livewire::actingAs($admin)
            ->test('admin::master-data.campuses')
            ->assertSet('name', 'Aparri')
            ->set('name', 'Aparri Campus')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Campus::count());
        $this->assertDatabaseHas('campuses', ['id' => $campus->id, 'name' => 'Aparri Campus']);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.campuses')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }
}
