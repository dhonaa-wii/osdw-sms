<?php

namespace Tests\Feature\Admin;

use App\Enums\DegreeLevel;
use App\Enums\UserRole;
use App\Models\College;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_programs_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/programs')->assertForbidden();
    }

    public function test_admin_can_create_a_program_under_a_college(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);

        Livewire::actingAs($admin)
            ->test('admin::master-data.programs')
            ->call('create')
            ->set('college_id', (string) $college->id)
            ->set('code', 'BSIT')
            ->set('name', 'BS Information Technology')
            ->set('degree_level', DegreeLevel::Undergraduate->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('programs', [
            'college_id' => $college->id,
            'code' => 'BSIT',
            'degree_level' => 'undergraduate',
        ]);
    }

    public function test_college_must_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.programs')
            ->call('create')
            ->set('college_id', '9999')
            ->set('code', 'BSIT')
            ->set('name', 'BS Information Technology')
            ->set('degree_level', DegreeLevel::Undergraduate->value)
            ->call('save')
            ->assertHasErrors(['college_id' => 'exists']);
    }

    public function test_admin_can_toggle_program_active_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $program = Program::create([
            'college_id' => $college->id, 'code' => 'BSIT', 'name' => 'BS IT',
            'degree_level' => DegreeLevel::Undergraduate, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.programs')
            ->call('toggleActive', $program->id);

        $this->assertDatabaseHas('programs', ['id' => $program->id, 'is_active' => false]);
    }
}
