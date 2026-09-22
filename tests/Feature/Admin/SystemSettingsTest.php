<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_system_settings(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/settings/system')->assertForbidden();
    }

    public function test_admin_can_save_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::settings.system')
            ->set('max_upload_size_mb', '20')
            ->set('public_results_visible', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('20', Setting::find('max_upload_size_mb')->value);
        $this->assertSame('0', Setting::find('public_results_visible')->value);
    }

    public function test_settings_persist_across_page_loads(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Setting::create(['key' => 'max_upload_size_mb', 'value' => '25']);

        Livewire::actingAs($admin)
            ->test('admin::settings.system')
            ->assertSet('max_upload_size_mb', '25');
    }

    public function test_upload_size_must_be_at_least_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test('admin::settings.system')
            ->set('max_upload_size_mb', '0')
            ->call('save')
            ->assertHasErrors(['max_upload_size_mb' => 'min']);
    }
}
