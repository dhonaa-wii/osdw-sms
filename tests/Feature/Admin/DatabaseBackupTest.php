<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_database_backup_page(): void
    {
        $this->get('/admin/database-backup')->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_database_backup_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/database-backup')->assertForbidden();
    }

    public function test_admin_sees_a_warning_when_the_connection_is_not_mysql(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/database-backup')
            ->assertOk()
            ->assertSeeText('not MySQL');
    }

    public function test_download_route_rejects_non_mysql_connections(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.database-backup.download'))
            ->assertStatus(422);
    }

    public function test_guest_cannot_hit_the_download_route(): void
    {
        $this->get(route('admin.database-backup.download'))->assertRedirect(route('login'));
    }
}
