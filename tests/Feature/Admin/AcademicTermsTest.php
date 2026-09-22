<?php

namespace Tests\Feature\Admin;

use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_academic_terms_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/academic-terms')->assertForbidden();
    }

    public function test_admin_can_create_an_academic_term(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.academic-terms')
            ->call('create')
            ->set('school_year', '2025-2026')
            ->set('semester', Semester::First->value)
            ->set('start_date', '2025-08-01')
            ->set('end_date', '2025-12-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_terms', ['school_year' => '2025-2026', 'semester' => 'first']);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.academic-terms')
            ->call('create')
            ->set('school_year', '2025-2026')
            ->set('semester', Semester::First->value)
            ->set('start_date', '2025-12-15')
            ->set('end_date', '2025-08-01')
            ->call('save')
            ->assertHasErrors(['end_date' => 'after']);
    }

    public function test_setting_a_term_current_unsets_the_previous_current_term(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $termA = AcademicTerm::create([
            'school_year' => '2024-2025', 'semester' => Semester::Second,
            'start_date' => '2025-01-01', 'end_date' => '2025-05-01', 'is_current' => true,
        ]);
        $termB = AcademicTerm::create([
            'school_year' => '2025-2026', 'semester' => Semester::First,
            'start_date' => '2025-08-01', 'end_date' => '2025-12-15', 'is_current' => false,
        ]);

        Livewire::actingAs($admin)
            ->test('admin::master-data.academic-terms')
            ->call('setCurrent', $termB->id);

        $this->assertDatabaseHas('academic_terms', ['id' => $termA->id, 'is_current' => false]);
        $this->assertDatabaseHas('academic_terms', ['id' => $termB->id, 'is_current' => true]);
    }
}
