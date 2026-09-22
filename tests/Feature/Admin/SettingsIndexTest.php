<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_application_settings(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_sees_links_to_every_low_frequency_setting(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Campus')
            ->assertSee('Colleges')
            ->assertSee('Programs')
            ->assertSee('Providers')
            ->assertSee('Scholarship programs')
            ->assertSee('Academic terms')
            ->assertSee('Users');
    }
}
