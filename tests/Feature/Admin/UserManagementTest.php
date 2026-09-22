<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_user_management(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_a_staff_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('create')
            ->set('name', 'Juana Dela Cruz')
            ->set('email', 'juana@osdw.test')
            ->set('password', 'temporary123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'juana@osdw.test',
            'role' => UserRole::OsdwStaff->value,
        ]);

        $user = User::where('email', 'juana@osdw.test')->first();
        $this->assertTrue(Hash::check('temporary123', $user->password));
    }

    public function test_email_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['email' => 'taken@osdw.test']);

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('create')
            ->set('name', 'Juana Dela Cruz')
            ->set('email', 'taken@osdw.test')
            ->set('password', 'temporary123')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_admin_can_edit_a_user_without_touching_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['name' => 'Old Name', 'role' => UserRole::OsdwStaff]);
        $originalPassword = $staff->password;

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('edit', $staff->id)
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertSame('New Name', $staff->name);
        $this->assertSame(UserRole::OsdwStaff, $staff->role);
        $this->assertSame($originalPassword, $staff->password);
    }

    public function test_manage_users_only_lists_staff_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->assertSee($staff->name)
            ->assertDontSee($admin->name);
    }

    public function test_admin_account_cannot_be_edited_from_this_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('edit', $otherAdmin->id);
    }

    public function test_admin_can_reset_a_users_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $originalPassword = $staff->password;

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('resetPassword', $staff->id);

        $staff->refresh();
        $this->assertNotSame($originalPassword, $staff->password);
    }

    public function test_admin_can_deactivate_another_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('toggleActive', $staff->id);

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => false]);
    }

    public function test_admin_account_cannot_be_deactivated_from_this_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($admin)
            ->test('admin::user-management.index')
            ->call('toggleActive', $admin->id);
    }
}
