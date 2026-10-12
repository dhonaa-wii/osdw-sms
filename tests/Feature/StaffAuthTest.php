<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Log in');
    }

    public function test_guest_is_redirected_away_from_dashboard(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@osdw.test',
            'password' => bcrypt('password'),
            'role' => UserRole::OsdwStaff,
        ]);

        Livewire::test('auth::login')
            ->set('email', 'staff@osdw.test')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'staff@osdw.test',
            'password' => bcrypt('password'),
            'role' => UserRole::OsdwStaff,
        ]);

        Livewire::test('auth::login')
            ->set('email', 'staff@osdw.test')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'inactive@osdw.test',
            'password' => bcrypt('password'),
            'role' => UserRole::OsdwStaff,
            'is_active' => false,
        ]);

        Livewire::test('auth::login')
            ->set('email', 'inactive@osdw.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_sees_full_sidebar_including_admin_only_items(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Applications')
            ->assertSee('Audit Log')
            ->assertSee('Scholarship Programs')
            ->assertSee('Providers')
            ->assertSee('Users')
            ->assertSee('Settings')
            ->assertSeeInOrder(['Audit Log', 'Users', 'Settings']);
    }

    public function test_osdw_staff_sidebar_hides_admin_only_items(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Applications')
            ->assertDontSee('Audit Log')
            ->assertDontSee('Scholarship Programs')
            ->assertDontSee('Providers');
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
