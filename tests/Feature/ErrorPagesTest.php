<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_renders_the_custom_view(): void
    {
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Return to homepage');
    }

    public function test_403_page_renders_the_custom_view_with_a_dashboard_link_for_authenticated_users(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get('/admin/settings')
            ->assertForbidden()
            ->assertSee("You don't have access")
            ->assertSee('Go to your dashboard');
    }

    public function test_419_view_renders(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('Your session expired', $html);
        $this->assertStringContainsString('Go back', $html);
    }

    public function test_500_view_renders(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('Something went wrong', $html);
        $this->assertStringContainsString('Return to homepage', $html);
    }
}
