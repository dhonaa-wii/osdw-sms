<?php

namespace Tests\Feature\Staff;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_announcements_page(): void
    {
        $this->get('/staff/announcements')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_announcements_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/staff/announcements')->assertOk();
    }

    public function test_staff_can_create_a_general_announcement_as_a_draft(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('create')
            ->set('title', 'Office closed on August 21')
            ->set('body', 'OSDW will be closed for a national holiday.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('announcements', [
            'title' => 'Office closed on August 21',
            'program_offering_id' => null,
            'is_published' => false,
        ]);
    }

    public function test_staff_can_publish_a_new_announcement_directly_from_the_modal(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('create')
            ->set('title', 'Second shortlist published')
            ->set('body', 'Check your status for details.')
            ->call('save', true)
            ->assertHasNoErrors();

        $announcement = Announcement::where('title', 'Second shortlist published')->firstOrFail();
        $this->assertTrue($announcement->is_published);
        $this->assertNotNull($announcement->published_at);
    }

    public function test_publishing_a_draft_while_editing_keeps_the_original_publish_date_on_further_edits(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $announcement = Announcement::create([
            'title' => 'Draft notice', 'body' => 'Body text.', 'created_by' => $staff->id,
        ]);

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('edit', $announcement->id)
            ->assertSet('editingIsPublished', false)
            ->call('save', true)
            ->assertHasNoErrors();

        $announcement->refresh();
        $this->assertTrue($announcement->is_published);
        $publishedAt = $announcement->published_at;

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('edit', $announcement->id)
            ->assertSet('editingIsPublished', true)
            ->set('body', 'Updated body text.')
            ->call('save')
            ->assertHasNoErrors();

        $announcement->refresh();
        $this->assertTrue($announcement->is_published);
        $this->assertTrue($announcement->published_at->equalTo($publishedAt));
        $this->assertSame('Updated body text.', $announcement->body);
    }

    public function test_staff_can_publish_and_unpublish_an_announcement(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $announcement = Announcement::create([
            'title' => 'Draft notice', 'body' => 'Body text.', 'created_by' => $staff->id,
        ]);

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('togglePublish', $announcement->id);

        $announcement->refresh();
        $this->assertTrue($announcement->is_published);
        $this->assertNotNull($announcement->published_at);

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('togglePublish', $announcement->id);

        $this->assertFalse($announcement->refresh()->is_published);
    }

    public function test_title_is_required(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        Livewire::actingAs($staff)
            ->test('staff::announcements.index')
            ->call('create')
            ->set('body', 'Body text.')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }
}
