<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_email_log(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)->get('/admin/email-log')->assertForbidden();
    }

    public function test_guest_cannot_access_email_log(): void
    {
        $this->get('/admin/email-log')->assertRedirect(route('login'));
    }

    public function test_admin_sees_logged_emails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        EmailLog::create([
            'recipient' => 'juan@example.com', 'subject' => 'Screening result',
            'type' => 'screening_result', 'status' => 'sent', 'sent_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('admin::email-log.index')
            ->assertSee('juan@example.com')
            ->assertSee('Sent');
    }

    public function test_status_filter_narrows_results(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        EmailLog::create([
            'recipient' => 'sent@example.com', 'subject' => 'Award notice',
            'type' => 'award_notice', 'status' => 'sent', 'sent_at' => now(),
        ]);
        EmailLog::create([
            'recipient' => 'failed@example.com', 'subject' => 'Award notice',
            'type' => 'award_notice', 'status' => 'failed', 'error_message' => 'Connection refused',
        ]);

        Livewire::actingAs($admin)
            ->test('admin::email-log.index')
            ->set('status', 'failed')
            ->assertSee('failed@example.com')
            ->assertDontSee('sent@example.com');
    }
}
